<h3>Verify Your Email</h3>

<div><br/>
	<p>For the following contact information verification is required:</p>
	<ul>
	<li>{$email} <a href="clientarea.php?action=domaindetails&domainid={$domainid}&modop=custom&a=send" class="btn btn-default">Resend email</a></li>
	</ul>
</div>
<form method="post" action="clientarea.php?action=domaindetails">
	<input type="hidden" name="id" value="{$domainid}" />
	<p><input type="submit" class="btn" value="« Back"></p>
</form>

