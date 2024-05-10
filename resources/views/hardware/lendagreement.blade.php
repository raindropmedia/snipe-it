<!DOCTYPE html>
<html>
<head>
<title>Generate PDF using Laravel TCPDF - ItSolutionStuff.com</title>
<style type="text/css">
td {
    padding-top: 10px;
    padding-bottom: 10px;
}
p {padding-top: 10px;
    padding-bottom: 10px;}
</style>
</head>

<body style="font-size: small;"><br><br>
    <table width="100%" border="0">
      <tbody>
        <tr>
          <td width="15%">&nbsp;</td>
          <td width="55%">&nbsp;</td>
          <td width="30%"><img src="{{$company_logo}}"></td>
        </tr>
        <tr>
          <td colspan="2">{{$contact_name}}<br>{{$contact_email}} <br>{{$contact_phone}}
          </td>
          <td>{{$company_name}}<br>{{$company_email}}</td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td colspan="2"><h2>Leihschein: {{ $request_id }}</h2></td>
          <td>{{$today}}</td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td>Angefragt: </td>
          <td>{{$requested_date}}</td>
          <td>&nbsp;</td>
        </tr>
		  <tr>
		    <td>Abholung:</td>
          <td>{{ $expected_checkout }}</td>
          <td>&nbsp;</td>
        </tr>
		  <tr>
		    <td>Rückgabe:</td>
          <td>{{ $expected_checkin }}</td>
          <td>&nbsp;</td>
        </tr>
		  <tr>
		    <td>Notizen:</td>
          <td colspan="2">{{ $contact_notes }}</td>
        </tr>
      </tbody>
</table>
	<br>
	<hr>
    <br>
	<br>
    <table width="100%" border="0">
      <tbody>
        <tr>
          <td width="15%"><strong>Asset Tag</strong></td>
          <td width="85%">{{$asset_tag}}</td>
        </tr>
        <tr>
          <td>Bezeichnung</td>
          <td>{{$asset_name}}</td>
        </tr>
        <tr>
          <td>Hersteller</td>
          <td>{{$manufacturer}}</td>
        </tr>
        <tr>
          <td>Standort</td>
          <td>{{$location_name}} - {{$location_address}}</td>
        </tr>
        <tr>
          <td valign="top">Zubehör</td>
          <td><?php $order   = array("\r\n", "\n", "\r");
		echo str_replace($order, '<br>', $asset_snipeit_zubehar_2);
			  ?></td>
        </tr>
      </tbody>
</table>
	<br>
	<hr>
	<br>
	<p>Hiermit bestätige ich den Erhalt ober genannter Geräte. Artikel sind selber auf Vollständigkeit zu prüfen.<br><span style="color: #FF0000">Für Verlust und durch unsachgemäße oder unbefugte Nutzung entstandene Schäden ist Ersatz zu leisten. <br>Ich bestätige eine Haftpflichtversicherung für gemietete Geräte abgeschlossen zu haben!<br>Die Weitergabeder geliehenen Geräte an Dritte ist untersagt.<br>
(Ausnahme bei Veranstaltungsbeauftragten - weitergabe nur zu gleichen Bedingungen).<br>Zuwiederhandlungen schließen eine zukünftige Nutzung aus!</span></p>
	<p>&nbsp;</p>
	<p>Artikel laut obriger Aufstellung vollständig erhalten am ____________________________</p>
	<p>&nbsp;</p>
	<p>Unterschrift: ____________________________</p>


</body>

</html>