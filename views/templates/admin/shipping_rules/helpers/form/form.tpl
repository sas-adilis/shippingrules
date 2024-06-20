{extends file="helpers/form/form.tpl"}
{block name="input"}
    {if $input.type == 'amount_taxes'}

        {if isset($fields_value[$input.name|cat:"_currency"])}
            {assign var='selected_currency' value=$fields_value[$input.name|cat:"_currency"]}
        {else}
            {assign var='selected_currency' value=''}
        {/if}

        {if isset($fields_value[$input.name|cat:"_tax"])}
            {assign var='selected_tax' value=$fields_value[$input.name|cat:"_tax"]}
        {else}
            {assign var='selected_tax' value=''}
        {/if}

        <div class="row">
            <div class="col-lg-7">
                {if isset($input.maxchar) || isset($input.prefix) || isset($input.suffix)}
                    <div class="input-group{if isset($input.class)} {$input.class}{/if}">
                {/if}
                        <input type="text"
                               name="{$input.name}"
                               id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}"
                               value="{$fields_value[$input.name]|escape:'html':'UTF-8'}"
                               class="{if isset($input.class)}{$input.class}{/if}{if $input.type == 'tags'} tagify{/if}"
                        />
                {if isset($input.suffix)}
                    <span class="input-group-addon">
                      {$input.suffix}
                    </span>
                {/if}
                {if isset($input.maxchar) || isset($input.prefix) || isset($input.suffix)}
                    </div>
                {/if}
            </div>
            <div class="col-lg-2">
                <select name="{$input.name}_currency">
                    {foreach from=$input.currencies item=currency}
                        <option value="{$currency.id_currency}"{if $currency.id_currency == $selected_currency} selected="selected"{/if}>{$currency.iso_code}</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-lg-3">
                <select name="{$input.name}_tax">
                    <option value="0" {if 0 == $selected_tax} selected="selected"{/if}>HT</option>
                    <option value="1" {if 1 == $selected_tax} selected="selected"{/if}>TTC</option>
                </select>
            </div>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}