<h2>URL Forwarding</h2>
<div class="alert alert-info">
        Point your domain to a web site by pointing to an IP Address, or forward to another site, or point to a temporary page (known as Parking), and more. These records are also known as sub-domains.
</div>
{if $errormessage}<div class="alert alert-error alert-danger" style="display:block;">{$errormessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div>{/if}
{if $successmessage}<div class="alert alert-success" style="display:block;">{$successmessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div>{/if}
<div id="domainconfig" style="padding:0px 20px">
	<form  id="whmcsorderfrm" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=domainurlforwarding">
		<input type="hidden" name="id" value="{$domainid}" />
		<input type="hidden" name="modop" value="custom"/>
		<input type="hidden" name="a" value="domainurlforwarding"/>
		<table class="table table-striped">
			<thead>
				<tr>
					<th>Source</th>
					<th></th>
					<th>Type</th>
					<th>Address</th>
				</tr>
			</thead>
			{for $cnt=0 to $data|@count}
				{assign var="URLRedirected" value=""}
				{assign var="FrameRedirect" value=""}
				<tr>
					<td><input class="form-control" type="text" name="dnsrecordhost[]" value="{$data[$cnt].hostname}"/></td>
					<td>.{$domainName}</td>
					<td>
						{if $data[$cnt].type == "URL"}
							{assign var="URLRedirected" value="selected"}
						{else if $data[$cnt].type == "FRAME"}
							{assign var="FrameRedirect" value="selected"}
						{/if}
						<select class="form-control" name="dnsrecordtype[]">
							<option value="URL" {$URLRedirected}>URL Redirect</option>
							<option value="FRAME" {$FrameRedirect}>URL Frame</option>
						</select>
					</td>
					<td><input class="form-control" name="dnsrecordaddress[]" type="text" value="{$data[$cnt].address}"/></td>
				</tr>
			{/for}
		</table>
		<p align="center">
			<input class="btn btn-primary" type="submit" value="Update"/>
		</p>
	</form>
</div>
<form method="post" action="clientarea.php?action=domaindetails">
	<input type="hidden" name="id" value="{$domainid}" />
	<p><input type="submit" class="btn" value="« Back"></p>
</form>
