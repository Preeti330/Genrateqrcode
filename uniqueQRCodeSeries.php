<?php

 function actionGenerateqrcodes($program_id)
    {
        //generate random unquie codes and store in qr_genartaion_details
        //generate qrcodes with text as unquie code . and then store in file 
        //after all this generate an zip file 
        $flag = 0;
        $date = date('Y-m-d H:i:s');
        $dateformat = date('Y-m-d');
        $qr_approve_id = Yii::$app->request->post('qr_approve_id');
        $checkQrreport =
            QrGenartaionConfigDetail::find()->where(['id' => $qr_approve_id])->andWhere(['program_id' => $program_id])->one();

        if (!empty($checkQrreport) && $checkQrreport != null) {
            $connection = \Yii::$app->db;
            $transaction = $connection->beginTransaction();

            if ($checkQrreport['approval_status'] != 0) {
                $this->throwException(422, "The request already approved");
            }

            try {
                $checkQrreport->approval_status = 1;
                if (!empty($checkQrreport['sku']) && $checkQrreport['sku'] != null) {

                    // $qrpath =   
                    // $checkQrreport['id']."/".$checkQrreport['sku'].'_'.$checkQrreport['qr_datail_id'].'_'.date('Y_m_d');
                    $pathdir    = 'uploads/qrcodes/' . uniqid() . "_" . $checkQrreport['id'];

                    $checkBrand = Brands::find()->where(['id' => $checkQrreport['sku']])->andWhere(['program_id' => $program_id])->andWhere(['status' => 1])->one();

                    if (!empty($checkBrand) && $checkBrand != null) {

                        // $random_series = $this->generateQrSeries($checkQrreport['qty']);

                        //    if(count($random_series) == $checkQrreport['qty']){
                        if ($checkQrreport['qty']) {
                            $checkArray = [];
                            $records = [];

                            for ($i = 1; $i <= ($checkQrreport['qty']); $i++) {

                                $random_series = $this->generateQrSeries($checkArray);

                                //generate qr img
                                $path = $this->generateQrCode($checkQrreport['id'], $random_series, $pathdir);

                                $records[] = [$checkQrreport['qr_datail_id'], $random_series, $dateformat, $program_id, 1, $date, $date, $path, $qr_approve_id];
                            }


                            if (!empty($records)) {
                                $update = Yii::$app->db->createCommand()->batchInsert(
                                    'qr_genartaion_details',
                                    ['qr_detail_id', 'unquie_code', 'qr_generated_date', 'program_id', 'status', 'created_date', 'updated_date', 'qr_url', 'qr_general_config_id'],
                                    $records
                                )->execute();

                                $zf = $this->createZip($checkQrreport['id'], $pathdir);

                                $updatefile = \yii\helpers\Url::home(true) . $zf;
                                $checkQrreport->zip_url = $updatefile;
                                if ($checkQrreport->save()) {
                                    $records = [];
                                    // $checkArray=[];
                                    unset($records);
                                    $flag = 1;
                                }
                            } else {
                                // Yii::error('No records to insert.');
                                $this->throwException(422, "No records");
                                $flag = 0;
                            }
                        } else {
                            $this->throwException(422, "Qty and random unquie code count is mismatched");
                        }
                    } else {
                        $this->throwException(422, "Sku not found");
                    }
                } else {
                    $this->throwException(422, "Sku not found");
                }
                if ($flag == 1) {
                    $transaction->commit();
                    return [
                        'url' => $updatefile
                    ];
                }
            } catch (\Exception $e) {
                $directoryToDelete = $pathdir;
                if (is_dir($directoryToDelete)) {
                    $files = glob("$directoryToDelete/*");

                    // Delete all files in the directory
                    array_map('unlink', $files);

                    // Remove the directory itself
                    rmdir($directoryToDelete);
                }

                $transaction->rollback();
                $this->throwException(422, $e);
                // $this->throwException(406, "Parameters Missing or Database Error..");
            }
        } else {
            $this->throwException(422, 'The requested not found...!!');
        }
    }

    function generateQrSeries(&$checkArray)
    {
        do {
            // Generate a random string
            $randomString = Yii::$app->db->createCommand("
                SELECT UPPER(SUBSTRING(md5(RANDOM()::TEXT), 1, 6)) AS random_string
            ")->queryScalar();


            //     $randomString = Yii::$app->db->createCommand("
            //     SELECT floor(RANDOM()*1000000)
            // ")->queryScalar();

            // Check if the random string is unique in both the database and the checkArray
            $isUnique = QrGenartaionDetail::find()->where(['unquie_code' => $randomString])->count() == 0 && !in_array($randomString, $checkArray);
        } while (!$isUnique);

        if ($isUnique) {
            // $checkArray[] = $randomString;
            array_push($checkArray, $randomString);
        }
        // Add the unique code to the checkArray

        return $randomString;
    }
    private function generateQrCode($qrpath, $code, $pathdir)
    {

        $ecc = 'L';
        $pixel_Size = 10;
        $frame_size = 10;
        $data = $code;

        // $path = 'uploads/qrcodes/'.$qrpath.'/'; 
        $path = $pathdir . "/";
        $file = $path . uniqid() . ".png";
        // print_r($file);exit;

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        // $file = $path.$qrpath. ".png";
        // $file = $path.$code."_".$qrpath.".png";

        QRcode::png($data, $file, $ecc, $pixel_Size, $frame_size);
        return $file;
    }

    private function createZip($id, $path)
    {
        // $pathdir    = 'uploads/qrcodes/'.$id."/";
        $pathdir    = $path . "/";
        $zipcreated = $pathdir . "Qrcode.zip";
        $zip        = new ZipArchive;


        if ($zip->open($zipcreated, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if ($dir = opendir($pathdir)) {
                while (($file = readdir($dir)) !== false) {
                    if (is_file($pathdir . $file)) {
                        $zip->addFile($pathdir . $file, $file);
                    }
                }
                //    $zip->saveAs();


                closedir($dir);
                return $zipcreated;
            } else {
                throw new Exception("Unable to open directory: $pathdir");
            }



            // print_r($zipcreated);exit;

            // header('Content-Type: application/zip');
            // header('Content-disposition: attachment; filename=' . $zipcreated);
            // header('Content-Length: ' . filesize($zipcreated));
            // readfile($zipcreated);
            $zip->close();
            // unlink($zipcreated);
        } else {
            throw new Exception("Failed to create ZIP file.");
        }

        //     $file = 'full_path_to_file/your_file_name.zip';


    }
    
     function throwException($errCode, $errMsg)
    {
        throw new \yii\web\HttpException($errCode, $errMsg);
    }


    /// for the above program which generates the multiple unquie code based on the qty and this all qr code are unquie and at end convert this unquie codes as zip file 
    //table starcture 
    // here intaily comfigure the qr , and then upload excel which includes, qty,points,expriydate,sku
    //and this excel data get stores in qr_genartaion_config_details
    /*
    CREATE TABLE "qr_genartaion_config_details" (
        "id" INTEGER NOT NULL DEFAULT 'nextval(''qr_genartaion_config_details_id_seq''::regclass)',
        "qr_datail_id" INTEGER NOT NULL,
        "region" VARCHAR NULL DEFAULT NULL,
        "user_role" VARCHAR NULL DEFAULT NULL,
        "sku" VARCHAR NULL DEFAULT NULL,
        "points" INTEGER NULL DEFAULT NULL,
        "qty" INTEGER NULL DEFAULT NULL,
        "qr_points" INTEGER NULL DEFAULT NULL,
        "program_id" INTEGER NULL DEFAULT NULL,
        "expriy_date" DATE NULL DEFAULT NULL,
        "excel_url" TEXT NULL DEFAULT NULL,
        "created_date" TIMESTAMP NULL DEFAULT 'CURRENT_TIMESTAMP',
        "updated_date" TIMESTAMP NULL DEFAULT 'CURRENT_TIMESTAMP',
        "zip_url" TEXT NULL DEFAULT NULL,
        "approval_status" INTEGER NULL DEFAULT '0',
        PRIMARY KEY ("id")
    )
    ;
    COMMENT ON COLUMN "qr_genartaion_config_details"."id" IS 'Primary key of the table';
    COMMENT ON COLUMN "qr_genartaion_config_details"."qr_datail_id" IS 'Foreign key referencing qr_details table';
    COMMENT ON COLUMN "qr_genartaion_config_details"."region" IS 'Region associated with the QR code';
    COMMENT ON COLUMN "qr_genartaion_config_details"."user_role" IS 'User role associated with the QR code';
    COMMENT ON COLUMN "qr_genartaion_config_details"."sku" IS 'SKU associated with the QR code';
    COMMENT ON COLUMN "qr_genartaion_config_details"."points" IS 'Points associated with the configuration';
    COMMENT ON COLUMN "qr_genartaion_config_details"."qty" IS 'Quantity associated with the configuration';
    COMMENT ON COLUMN "qr_genartaion_config_details"."qr_points" IS 'QR points associated with the configuration';
    COMMENT ON COLUMN "qr_genartaion_config_details"."program_id" IS 'ID of the associated program';
    COMMENT ON COLUMN "qr_genartaion_config_details"."expriy_date" IS 'Expiration date of the configuration';
    COMMENT ON COLUMN "qr_genartaion_config_details"."excel_url" IS 'URL of the related Excel file';
    COMMENT ON COLUMN "qr_genartaion_config_details"."created_date" IS 'Date when the record was created';
    COMMENT ON COLUMN "qr_genartaion_config_details"."updated_date" IS 'Date when the record was last updated';
    COMMENT ON COLUMN "qr_genartaion_config_details"."zip_url" IS '';
    COMMENT ON COLUMN "qr_genartaion_config_details"."approval_status" IS '0 for pending , 1for approved';

    */

    //qr generation details table

    /*
    CREATE TABLE "qr_genartaion_details" (
	"id" INTEGER NOT NULL DEFAULT 'nextval(''qr_genartaion_details_id_seq''::regclass)',
	"qr_detail_id" INTEGER NOT NULL,
	"unquie_code" VARCHAR NULL DEFAULT NULL,
	"qr_url" TEXT NULL DEFAULT NULL,
	"qr_generated_date" DATE NULL DEFAULT NULL,
	"program_id" INTEGER NULL DEFAULT NULL,
	"status" SMALLINT NULL DEFAULT '0',
	"expired_status" SMALLINT NULL DEFAULT '0',
	"user_id" INTEGER NULL DEFAULT NULL,
	"points" INTEGER NULL DEFAULT NULL,
	"tarnsaction_date" DATE NULL DEFAULT NULL,
	"created_date" TIMESTAMP NULL DEFAULT 'CURRENT_TIMESTAMP',
	"updated_date" TIMESTAMP NULL DEFAULT 'CURRENT_TIMESTAMP',
	"sales_trans_id" INTEGER NULL DEFAULT NULL,
	"is_code_type" INTEGER NULL DEFAULT NULL,
	"qr_general_config_id" INTEGER NULL DEFAULT NULL,
	PRIMARY KEY ("id"),
	UNIQUE INDEX "unique_code" ("unquie_code"),
	INDEX "unquie_index_id" ("unquie_code")
)
;
COMMENT ON COLUMN "qr_genartaion_details"."id" IS 'Primary key of the table';
COMMENT ON COLUMN "qr_genartaion_details"."qr_detail_id" IS 'Foreign key referencing qr_details table';
COMMENT ON COLUMN "qr_genartaion_details"."unquie_code" IS 'Unique code associated with the QR';
COMMENT ON COLUMN "qr_genartaion_details"."qr_url" IS 'URL of the generated QR code';
COMMENT ON COLUMN "qr_genartaion_details"."qr_generated_date" IS 'Date when the QR code was generated';
COMMENT ON COLUMN "qr_genartaion_details"."program_id" IS 'ID of the associated program';
COMMENT ON COLUMN "qr_genartaion_details"."status" IS '';
COMMENT ON COLUMN "qr_genartaion_details"."expired_status" IS '0 for not used,1 for used by customer';
COMMENT ON COLUMN "qr_genartaion_details"."user_id" IS '';
COMMENT ON COLUMN "qr_genartaion_details"."points" IS '';
COMMENT ON COLUMN "qr_genartaion_details"."tarnsaction_date" IS '';
COMMENT ON COLUMN "qr_genartaion_details"."created_date" IS 'Date when the record was created';
COMMENT ON COLUMN "qr_genartaion_details"."updated_date" IS 'Date when the record was last updated';
COMMENT ON COLUMN "qr_genartaion_details"."sales_trans_id" IS '';
COMMENT ON COLUMN "qr_genartaion_details"."is_code_type" IS '';
COMMENT ON COLUMN "qr_genartaion_details"."qr_general_config_id" IS '';

    */ 
     

?>