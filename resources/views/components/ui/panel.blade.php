{{--
    Detail panel. It exists exactly once per page; the lists fill it with
    the content of their detail slot when a row is clicked.
--}}
<div id="ui-panel-scrim" class="ui-panel-scrim hidden" data-panel-close></div>

<aside id="ui-panel" class="ui-panel hidden" role="dialog" aria-modal="true" aria-labelledby="ui-panel-title" tabindex="-1">
    <div class="ui-panel-head">
        <h2 id="ui-panel-title" class="ui-card-title"></h2>
        <x-ui.button variant="ghost" size="sm" icon="x" :label="__('translation.close')" data-panel-close />
    </div>
    <div class="ui-panel-body" id="ui-panel-body"></div>
    <div class="ui-panel-foot hidden" id="ui-panel-foot"></div>
</aside>
