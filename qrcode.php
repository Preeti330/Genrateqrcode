<?php
include 'phpqrcode/qrlib.php';
 function GenarateQrcode()
{
  
    //Genarate QR Code Using QRCode Libary
    //it use the parameter like $data=This parameter gives the message which needs to be in QR code. It is mandatory parameter.
  // and here its url of my project api
    //in $data i am supplying restaurant_id as unqiue for each resutants QRCODE 
    //$file -- this the url to store qr code
    //$ecc -- this is regrading with qr image size and dimentions   
    $ecc = 'L';
    $pixel_Size = 10;
    $frame_size = 10;
    $resturant_id = $_GET['resturant_id'];
    $data = 'https://http://localhost/sample/qrcode.php?restaurant_id='. $resturant_id;
    $path = 'C:\Users\User\Desktop\qrcodes';
    $file = $path . uniqid() . ".png";
    $qrcode = QRcode::png($data, $file, $ecc, $pixel_Size, $frame_size);
    return "Sucessfully Genarated QR Code ..";
}

GenarateQrcode();

?>