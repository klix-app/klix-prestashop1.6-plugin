{if $page_name == "product"}
	{if $one_click_button_enabled }
		<a data-url={$url} data-ipa={$id_product_attribute} id='spellpayment' class='btn btn-primary'
			style='display:none;justify-content:center;margin:10px;align-items: center;' id='spellpayment' class='btn btn-primary'>
			<img src="{$this_path_bw}logo.png" alt="{l s='Klix' mod='spellpayment'}" width="86" height="49"
				style="object-fit: contain;" />
			{l s='Pay now' d='Modules.Spellpayment.Paynow'}
		</a>
	{/if}
{elseif $page_name == "cart" && !empty($cart.products) }
	{if $one_click_button_enabled }
		<a data-url={$url} style='display:inline-flex;justify-content:center;float:right;align-items: center;' id='spellpayment'
			class='btn btn-primary'>
			<img src="{$this_path_bw}logo.png" alt="{l s='Klix' mod='spellpayment'}" width="86" height="49"
				style="object-fit: contain;" />
			{l s='Pay now' d='Modules.Spellpayment.Paynow'}
		</a>
	{/if}
{elseif $page_name == "order"}
	{if $one_click_button_enabled}
		<a data-url={$url} style='display:flex;justify-content:center;margin:10px 0px; align-items: center;' id='spellpayment'
			class='btn btn-primary'>
			<img src="{$this_path_bw}logo.png" alt="{l s='Klix' mod='spellpayment'}" width="86" height="49"
				style="object-fit: contain;" />
			{l s='Pay now' d='Modules.Spellpayment.Paynow'}
		</a>
	{/if}
{/if}