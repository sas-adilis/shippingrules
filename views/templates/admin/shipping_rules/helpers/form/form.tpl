{**
* @author    Adilis <support@adilis.fr>
* @copyright 2024 SAS Adilis
* @license   http://www.adilis.fr
*}

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
                    <div class="input-group{if isset($input.class)} {$input.class|escape:'html':'UTF-8'}{/if}">
                {/if}
                        <input type="text"
                               name="{$input.name|escape:'html':'UTF-8'}"
                               id="{if isset($input.id)}{$input.id}{else}{$input.name|escape:'html':'UTF-8'}{/if}"
                               value="{$fields_value[$input.name]|escape:'html':'UTF-8'}"
                               class="{if isset($input.class)}{$input.class|escape:'html':'UTF-8'}{/if}{if $input.type == 'tags'} tagify{/if}"
                        />
                {if isset($input.suffix)}
                    <span class="input-group-addon">
                      {$input.suffix|escape:'html':'UTF-8'}
                    </span>
                {/if}
                {if isset($input.maxchar) || isset($input.prefix) || isset($input.suffix)}
                    </div>
                {/if}
            </div>
            <div class="col-lg-2">
                <select name="{$input.name|escape:'html':'UTF-8'}_currency">
                    {foreach from=$input.currencies item=currency}
                        <option value="{$currency.id_currency|intval}"{if $currency.id_currency == $selected_currency} selected="selected"{/if}>{$currency.iso_code|escape:'html':'UTF-8'}</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-lg-3">
                <select name="{$input.name|escape:'html':'UTF-8'}_tax">
                    <option value="0" {if 0 == $selected_tax} selected="selected"{/if}>HT</option>
                    <option value="1" {if 1 == $selected_tax} selected="selected"{/if}>TTC</option>
                </select>
            </div>
        </div>
    {elseif $input.name == 'id_country'}

        <select name="{$input.name|escape:'html':'UTF-8'}"
                class="{if isset($input.class)}{$input.class|escape:'html':'UTF-8'}{/if} fixed-width-xl"
                id="{if isset($input.id)}{$input.id|escape:'html':'UTF-8'}{else}{$input.name|escape:'html':'UTF-8'}{/if}"
                >
            {if isset($input.options.default)}
                <option value="{$input.options.default.value|escape:'html':'UTF-8'}" data-id-zone="-1">{$input.options.default.label|escape:'html':'UTF-8'}</option>
            {/if}

            {foreach $input.options.query AS $option}
                    <option value="{$option[$input.options.id]|escape:'html':'UTF-8'}"
                            {if isset($input.multiple)}
                                {foreach $fields_value[$input.name] as $field_value}
                                    {if $field_value == $option[$input.options.id]}
                                        selected="selected"
                                    {/if}
                                {/foreach}
                            {else}
                                {if $fields_value[$input.name] == $option[$input.options.id]}
                                    selected="selected"
                                {/if}
                            {/if}
                            data-id-zone="{$option.id_zone|intval}"
                    >{$option[$input.options.name]|escape:'html':'UTF-8'}</option>
            {/foreach}
        </select>
    {elseif $input.type == 'separator'}
        <div class="separator">
            {$input.content|escape:'htmlall':'UTF-8'}
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}