<div class="row">
	<div class="col-xs-12">
		<p class="payment_module">
		<form class="spellpayment-method-parameters" action={$action_url}>
			<input type="hidden" name="fc" value="module" />
			<input type="hidden" name="module" value="spellpayment" />
			<input type="hidden" name="controller" value="maincheckout" />
			<div style="color: red" class="error-message-panel"></div>
			<div class="form-horizontal spell-payment">
				{if $payment_method_selection_enabled}
					<div class="payment-method-select">
						<div data-countries-available={$country_options|@count}>
							<label>
								<select name="country" class="form-control" id="spell-country" title="Country">
									{foreach $country_options item=country}
										<option value={$country} {if $country == "any"} selected="selected" {/if}>
											{$payment_methods_api_data['country_names'][$country]}
										</option>
									{/foreach}
								</select>
							</label>
						</div>

						<span class="payment-method-list">
							{foreach $by_method item=data}
								<label style="padding: 1em; width: 250px;">
									<input type="radio" required="required" name="spell_payment_method"
										class="spell-payment-method" value="{$data['payment_method']}"
										data-countries={$data['countries']|json_encode|escape} />
									<div style="font-size: 14px;">{$payment_methods_api_data['names'][$data.payment_method]}
									</div>
									{assign var="logo" value=$payment_methods_api_data['logos'][$data.payment_method] scope="global"}
									{if ($logo|is_array)}
										<span style="display: block; padding-bottom: 3px; min-width: 200px; max-width: 200px;">
											{foreach from=$logo item=$i key=$key}
												<img src="https://portal.klix.app{$i}" width="40" height="35"
													style="margin: 0 10px 10px 0; float: left;" />
											{/foreach}
											<div class=" clear-div">
											</div>
										</span>
									{else}

										<div>
											<img src="https://portal.klix.app{$logo}" height='30'
												style='max-width: 160px; max-height: 30px; margin-bottom: 18px;' />
										</div>
									{/if}
								</label>
							{/foreach}
						</span>
					</div>
					<div style="display: flex; justify-content: end;">
					<button type="submit" name="proceedPayment" class="button btn btn-default standard-checkout button-medium" style="">
							<span>
								{l s='Proceed to checkout' mod='Modules.Spellpayment.Payment'}
								<i class="icon-chevron-right right"></i>
							</span>
						</button>
					</div>
				{/if}
			</div>
			<script>
				window.addEventListener('load', () => {
					const spellFilterPMs = (spellCountryInp) => {
						const selected = spellCountryInp.value;
						const els = document.getElementsByClassName(" spell-payment-method");
						let first = true;
						for (let i = 0; i < els.length; i++) {
							const el = els[i];
							const countries = JSON.parse(el.getAttribute("data-countries"));
							const includes = countries.some(c => [selected, 'any'].includes(c));
							el.closest('label').style.display = !includes ? "none" :'block';
							if(el.checked){
								el.click();
							}
							if (includes && first) {
								first = false;
								el.click();
							}
						}
					};

					const initializeCountrySelect = () => {
						const spellCountryInp = document.getElementById("spell-country");
						if (spellCountryInp) {
							var selected = {$selected_country|json_encode};
							if (selected) {
								spellCountryInp.value = selected;
								spellCountryInp.dispatchEvent(new Event('change'));
							}
							spellCountryInp.addEventListener("change", () => spellFilterPMs(spellCountryInp));

							spellFilterPMs(spellCountryInp);
						}
					};

					const main = () => {
						let error = {$error|json_encode}

						if (error) {
							console.debug('Klix.app payments error', error);
							const selector = '.spellpayment-method-parameters .error-message-panel';
							[...document.querySelectorAll(selector)]
							.forEach(pan => pan.textContent = error);
						} else {
							// initialize form
							[...document.querySelectorAll('input[type="radio"][data-module-name="spellpayment"]')]
							.forEach(el => el.checked = true);
							[...document.querySelectorAll('.spellpayment-method-parameters')]
							.forEach(el => el.parentNode.style.display = "unset");
							initializeCountrySelect();
						}
					};

					main();
				});
			</script>

			<style>
				.spell-payment {
					padding: 0px 0px;
				}

				.spell-payment .payment-method-select {
					background-color: transparent;
					padding: 8px;
				}

				.spell-payment .payment-method-select>[data-countries-available] {
					margin-left: 8px;
					margin-top: 4px;
				}

				.spell-payment .payment-method-select>[data-countries-available="1"],
				.spell-payment .payment-method-select>[data-countries-available="0"] {
					display: none;
				}

				.spell-payment .payment-method-list {
					display: flex;
					flex-flow: row wrap;
					margin: 8px;
				}

				.spell-payment .payment-method-list>label {
					text-align: left;
					/* background-color: #dedede; */
					margin-right: 8px;
					border-radius: 15px;
					padding: 6px;
				}

				.spell-payment .payment-method-list .clear-div {
					clear: both;
					background-color: transparent;
				}

				input[type="radio"][name="payment_method"][value="spell_payment"]:not(:checked)+* .payment-method-select {
					opacity: 0.2;
					pointer-events: none;
				}
			</style>

		</form>
		</p>
	</div>
</div>