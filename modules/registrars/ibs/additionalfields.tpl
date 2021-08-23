<h2>Domain Configuration</h2>
<p>The following options and settings are available for the domains you have chosen. Required fields are indicated with a *.</p><br>
<h3>{$domainName}</h3><hr>
{if $errormessage}<div class="alert alert-error alert-danger" style="display:block;">{$errormessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div>{/if}
{if $successmessage}<div class="alert alert-success" style="display:block;">{$successmessage|replace:'<li>':' &nbsp;#&nbsp; '} &nbsp;&nbsp; </div>{/if}
<div id="domainconfig" style="padding:0px 20px">
	<form  id="whmcsorderfrm" method="post" action="clientarea.php?action=domaindetails&id={$domainid}&modop=custom&a=additionalfields">
		<input type="hidden" name="id" value="{$domainid}" />
		<input type="hidden" name="modop" value="custom"/>
		<input type="hidden" name="a" value="additionalfields"/>
		<table>
		{foreach from=$whoisContacts item="contactName"}
			{if $additionalFieldValue.$contactName|@count > 0}
				{if $contactName !== "other"}
					<tr><td colspan="2"><h3>{$contactName|@ucfirst}</h3><hr/></td></tr>
				{/if}
					{foreach from=$additionalfields key=index item=content}
						{assign var="name" value=$content.Name}
						{assign var="name" value=$name|@strtolower}
						{if isset($content.Required) && $content.Required}
							{assign var="required" value="required"}
						{else}
							{assign var="required" value=""}
						{/if}
						{assign var="fieldName" value=$contactName|cat:'_'|cat:$content.Name|@strtolower}
						{if (isset($content.contactType) && $contactName|in_array:$content.contactType || !isset($content.contactType))}
							<tr>
								{if ($content.Type == "text" && $contactName !== 'other') || ($content.Type == "text" && $contactName == 'other' && $additionalFieldValue.$contactName.$name)}
									<td>{$content.DisplayName}</td>									
									<td><input type="textbox" name="{$fieldName}" value="{$additionalFieldValue.$contactName.$name}" {$required}></td>
								{elseif ($content.Type == "dropdown" && $contactName !== 'other') || ($content.Type == "dropdown" && $contactName == 'other' && $additionalFieldValue.$contactName.$name)}
									{assign var="options" value=","|explode:$content.Options}
									<td>{$content.DisplayName}</td>									
									<td><select name="{$fieldName}" {$required}>
										{foreach from=$options key=value item=displayName}
											{if $displayName|strpos:"|" > 0}
												{assign var="data" value="|"|explode:$displayName}
												{if $additionalFieldValue.$contactName.$name|@strtolower == $data.0|@strtolower}
													{assign var="selected" value="selected"}
												{else}
													{assign var="selected" value=""}
												{/if}
												<option value="{$data.0}" {$selected}>{$data.1}</option>	
											{else}
												<option value="{$displayName}" {$selected}>{$displayName}</option>	
											{/if}
										{/foreach}
									</select></td>
								{elseif ($content.Type == "tickbox" && $contactName !== 'other') || ($content.Type == "tickbox" && $contactName == 'other' && $additionalFieldValue.$contactName.$name)}
									{if ($additionalFieldValue.$contactName.$name|@strtolower == "yes") || $additionalFieldValue.$contactName.$name == 1}
										{assign var="checked" value="checked"}
									{else}	
										{assign var="checked" value=""}
									{/if}
									<td>{$content.DisplayName}</td>									
									<td><input type="checkbox" name="{$fieldName}" {$required} {$checked}>
								{/if}
								{if $content.Required}*{/if}</td>
							</tr>
						{/if}
					{/foreach}
			{/if}
		{/foreach}
		</table>
		<p align="center">
			<input type="submit" value="Update"/>
		</p>
	</form>
</div>
<form method="post" action="clientarea.php?action=domaindetails">
	<input type="hidden" name="id" value="{$domainid}" />
	<p><input type="submit" class="btn" value="« Back"></p>
</form>
