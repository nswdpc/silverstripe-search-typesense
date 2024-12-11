<%-- override this template in your project or theme --%>
<div class="search-result">
    <h4><% if $Link %><a href="{$Link}">{$Title}</a><% else %>{$Title}<% end_if %></h4>
    <div class="search-result-content">
        <% if $Abstract %>
        {$Abstract}
        <% else_if $Description %>
        {$Description.FirstSentence}
        <% else %>
        {$Content.FirstSentence}
        <% end_if %>
    </div>
</div>
