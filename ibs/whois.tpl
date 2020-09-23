<div class="alert alert-block alert-info">Domain Name: <strong>{$domain}</strong></div>
{if $errormessage}<div class="alert alert-error alert-danger" style="display:block;">{$errormessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div><br />{/if}
{if $status}
	{if $successmessage}<div class="alert alert-success" style="display:block;">{$successmessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div><br />{/if}
	<div class="row">
		<div class="col30">
			<h1> Id Protection</h1>	
			<p>The public details for all contacts are completely replaced in the WHOIS data with Whois Privacy Corp. data in order to protect your identity and try to prevent spam from happening.</p>
		</div>
		<div class="col70">
			<h4><strong>Id Protection Status:</strong></h4>
			<p><Strong>{$status|@ucfirst}</strong></p>
			<hr/>
			<form method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=getwhois">
				<input type="hidden" name="id" value="{$domainid}" />
				<input type="hidden" name="modop" value="custom"/>
				<input type="hidden" name="a" value="getwhois"/>
				<input type="hidden" name="status" value="{$status}"/>
				{if $status !== "unknown"} 
					{if $status == "disabled"}
						<input type='submit' class='btn btn-success' value='Enable Id Protection'/>
					{else}
						<input type='submit' class='btn btn-danger' value='Disable Id Protection'/>
					{/if}	
				{/if}
			</form>
		</div>
	</div>
{/if}
<form method="post" action="clientarea.php?action=domaindetails">
			<input type="hidden" name="id" value="{$domainid}" />
	<p><input type="submit" class="btn" value="« Back"></p>
</form>

