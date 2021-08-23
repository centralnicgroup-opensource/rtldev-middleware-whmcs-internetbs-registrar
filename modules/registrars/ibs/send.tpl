<h3>Email Verification</h3>
	{if $successmessage}<div class="alert alert-success" style="display:block;">{$successmessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div><br />{/if}
	{if $errormessage}<div class="alert alert-error alert-danger" style="display:block;">{$errormessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div><br />{/if}
<form method="post" action="clientarea.php?action=domaindetails">
	<input type="hidden" name="id" value="{$domainid}" />
	<p><input type="submit" class="btn" value="« Back"></p>
	<div style="height:25px;"></div>
</form>

