{if $page_name == "order" || $page_name == "product" || $page_name == "cart" }
    <script type="text/javascript">
    if(document.getElementById("spellpayment")){
        document.getElementById("spellpayment").style.display = "flex";
    }
        document.getElementById("spellpayment").addEventListener("click", function() {
            let url = document.getElementById("spellpayment").getAttribute("data-url");
            {if $page_name == "product"}
                let qty = document.querySelector('[name=qty]');
                if (qty) {
                    let id_product_attribute = document.getElementById("spellpayment").getAttribute("data-ipa");
                    qty = qty.value;
                    url = url + "&id_product_attribute=" + id_product_attribute + "&qty=" + qty;
                }
            {/if}

            window.location.href = url;
        });
    </script>
{/if}

{$page_name}