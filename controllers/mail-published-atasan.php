<?php
use esk\models\Model;
use esk\components\Helper;
?>

<pre style='font-family: sans-serif;'>
Semangat Pagi,
Bapak/Ibu/Saudara/i <?= $data_esk->decree_nama ?>

<!--
Telah diterbitkan data eSK sesuai dengan eSK No. (<?php // $data_esk->number_esk ?>) 
terkait penugasan baru untuk Saudara/i <?php // $data_esk->nik ?>/<?php // $data_esk->nama ?>, berikut adalah detail datanya:
-->
<p>
	Telah disampaikan ke atasan karyawan, eSK dengan data sebagai berikut:
</p>

<table width='80%' border='0'>
    <tr>
        <td width='25%'>Nomor eSK</td>
        <td width='2%'>:</td>
        <td><?= $data_esk->number_esk ?></td>
    </tr>
    <tr>
        <td width='25%'>NIK</td>
        <td width='2%'>:</td>
        <td><?= $data_esk->nik ?></td>
    </tr>
    <tr>
        <td width='25%'>Nama Karyawan</td>
        <td width='2%'>:</td>
        <td><?= $data_esk->nama ?></td>
    </tr>
    <tr>
        <td width='25%'>Tentang</td>
        <td width='2%'>:</td>
        <td><?= ucwords($data_esk->about_esk) ?></td>
    </tr>
    <tr>
        <td width='25%'>Tanggal Penetapan eSK</td>
        <td width='2%'>:</td>
        <td><?= Model::TanggalIndo($data_esk->effective_esk_date) ?></td>
    </tr>
</table>
<br />

<p>
Demikian disampaikan, terima kasih
</p>

<br />
<p> 
Terima kasih
</p>

</pre>        