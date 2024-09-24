<%-- override this template in your project or theme --%>
<h4><% if $Link %><a href="{$Link}">{$Title}</a><% else %>{$Title}<% end_if %></h4>

<% if $Abstract %>
{$Abstract}
<% else_if $Description %>
{$Description}
<% end_if %>
