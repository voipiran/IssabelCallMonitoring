<div id="controlPanelApplication">



<script type="text/x-handlebars" data-template-name="desktop">
    <div class="containerAll">
        <div class="statusbar">
            <!-- TODO: i18n -->
            {literal}
                {{#if connected}}
                    <span style="color: green; font-weight: bold;">Connected</span>
                    <div class="utilities-container">


                        <div class="Extensions-Calls-Count">
                          <div class="Registered">
                            <span>Extensions Online</span><br>
                            <span class="Count-Registered" style="font-size: 35px; color: #06ea06c4; padding: 10px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;"></span>
                          </div>
                          <div class="NotRegistered">
                            <span>Extensions Offline</span><br>
                            <span class="Count-NotRegistered" style="font-size: 35px; color: red; padding: 10px; text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;"></span>
                          </div>
                        </div>
                    </div>

                {{else}}
                    <span style="color: red; font-weight: bold;">Connecting...</span>
                {{/if}}
            {/literal}
        </div>


		<div class="left-container" id="left-container">
        {literal}{{#view App.BaseSortableView }}{/literal}
        {* La lista de las extensiones no asignadas a alguna de las áreas *}
        {literal}
            {{#view App.PBXPanelView controllerBinding="extensions" }}
              <dt class="operator-title-container" id="operator-title-container">
                <i class="fa fa-phone" style="font-size:20px; padding-right:5px;"></i> {/literal}{$AREA_DESCR_EXTENSION}{literal}
              </dt>
                 <div class="Extensions-List" id="Extensions-List">
                    {{#if finishedloading}}
                        {{#view App.SortablePanelView }}
                            {{#each }}
                                  {{view App.ExtensionView}}
                            {{else}}
                                <br/>
                            {{/each}}
                        {{/view}}
                    {{else}}
                <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                {{/if}}
                </div>
            {{/view}}
        {/literal}
        {literal}{{/view}}{/literal}{* App.BaseSortableView *}

                {literal}{{#view App.FAQView }}{/literal}
        {literal}{{#view App.BaseSortableView }}{/literal}

        {* La lista de las extensiones asignadas al área 1 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area1" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 2 *}
                {literal}
                {{#view App.PBXPanelView controllerBinding="area2" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 3 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area3" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 4 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area4" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 5 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area5" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 6 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area6" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 7 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area7" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 8 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area8" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>

        {* La lista de las extensiones asignadas al área 9 *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="area9" }}
                    {{view App.EditableTitleView }}
                    <dd>
                    <div class="Extensions-List" id="Extensions-List">
                        {{#if finishedloading}}
                            {{#view App.SortablePanelView }}
                                {{#each }}
                                    {{view App.ExtensionView}}
                                {{else}}
                                    <br/>
                                {{/each}}
                                {{/view}}
                            {{else}}
                            <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                        {{/if}}
                    </div>
                    </dd>
                {{/view}}
            {/literal}</br>


        {literal}{{/view }}{/literal} {* App.BaseSortableView *}
        {literal}{{/view}}{/literal} {* App.FAQView *}
        
        </div>

        
        <div class="right-container">

        {literal}{{#view App.FAQView }}{/literal}
        {literal}{{#view App.BaseSortableView }}{/literal}
        
        {* La lista de las colas *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="queues" }}
                    <dt>{{description}}</dt>
                    <dd>
                        {{#if finishedloading}}
                            <div>
                                {{#each }}
                                    {{view App.QueueView class="QueueView-large"}}
                                {{else}}
                                    <br/>
                                {{/each}}
                            </div>
                    {{else}}
                        <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                    {{/if}}
                    </dd>
                {{/view}}
            {/literal}<br>

        {* La lista de las conferencias *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="conferences" }}
                    <dt>{{description}}</dt>
                    <dd>
                        {{#if finishedloading}}
                            <div>
                                {{#each }}
                                    {{view App.ConferenceView class="ConferenceView-large"}}
                                {{else}}
                                    <br/>
                                {{/each}}
                            </div>
                    {{else}}
                        <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                    {{/if}}
                    </dd>
                {{/view}}
            {/literal}<br>

        {* La lista de los parqueos *}
            {literal}
                {{#view App.PBXPanelView controllerBinding="parkinglots" }}
                    <dt>{{description}}</dt>
                    <dd>
                        {{#if finishedloading}}
                            <div class="parkinglots-list">
                                {{#each }}
                                    {{view App.ParkinglotView class="ParkinglotView-large"}}
                                {{else}}
                                    <br/>
                                {{/each}}
                            </div>
                    {{else}}
                        <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                    {{/if}}
                    </dd>
                {{/view}}
            {/literal}
         {literal}{{/view }}{/literal} {* App.BaseSortableView *}
         {literal}{{/view}}{/literal} {* App.FAQView *}
         </div>
            

        <div class="left-container"> 
        {literal}{{#view App.BaseSortableView }}{/literal}

        {* La lista de las troncales VoIP *}
        <div style="width:100%; float:left"> 
        {literal}
            {{#view App.PBXPanelView controllerBinding="iptrunks" }}
                <dt class="operator-title-container" id="operator-title-container";">
                    {/literal}{$AREA_DESCR_TRUNKSSIP}{literal}
                </dt>
                {{#if finishedloading}}
                    <div>
                        {{#each }}
                            {{view App.IPTrunkView }}
                        {{else}}
                            <br/>
                        {{/each}}
                    </div>
                {{else}}
                    <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                {{/if}}
            {{/view}}
        {/literal}
        </div>

        {* La lista de las troncales DAHDI *}
        <div style="width:50%; float:left; display: none;">	
        {literal}
            {{#view App.PBXPanelView controllerBinding="dahdi" }}
            <dt class="operator-title-container" id="operator-title-container";">
                {/literal}{$AREA_DESCR_TRUNKS}{literal}
              </dt>
                    {{#if finishedloading}}
                        <div>
                            {{#each }}
                                {{view App.DAHDISpanView }}
                            {{else}}
                                <br/>
                            {{/each}}
                        </div>
                    {{else}}
                <img class "icon" src="modules/{/literal}{$module_name}{literal}/images/loading.gif"/>
                {{/if}}
            {{/view}}
        {/literal}
        </div>

        {literal}{{/view}}{/literal}{* App.BaseSortableView *}
        </div>
        
    </div>
</script><!-- data-template-name="desktop"  -->



<script type="text/x-handlebars" data-template-name="extension">
<div style="float:left; border: black solid 0px; z-index:2;">
	<a class="pbxtooltip" href="#">
    	<img src="modules/{$module_name}/images/info.png"/>
    	<div>
    		{literal}
    		{{channel}} {{#if registered}} - ({{ip}}){{/if}}<br/>
            {{description}}<br/>
    		{{#if active }}
    		<ul>
    	    {{#each active}}
    	    <li>{{formatSince}}: {{remoteExten}}</li>
    	    {{/each}}
    	    </ul>
    	    {{/if}}
    		{/literal}
    	</div>
	</a>
</div>
<div style="float:left; width:115px; text-align:left; padding-left:4px;">
	{literal}
	<b>{{extension}}:</b> {{view.truncatedDescription}}<br/>
        <div class="line-monitor">
              <div class="line" style="width:30%">
              <span>Line1:</span> <br>
              <span>Line2:</span>
              </div>
              <div class="RemoteExtension"style="width:70%">
              {{#each active}}
                  <span class="monitor" title="{{Channel}}">{{formatSince}}: {{remoteExten}}</span><br>
              {{/each}}
            </div>
        </div>
	{/literal}
</div>

{literal}
<div class="phone-mail-container">
  <div class="phone-icon">
    {{view App.DraggablePhoneIconView iconBinding="view.extensionIcon"}}
  </div>
  {{#if unreadMail }}
  <div class="mail-icon">
    <a class="pbxtooltip">
      <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/mail.png" style="width:30px; height: 20px;" />
      <div>{/literal}{$LBL_NEW}{literal}: {{NewMessages}}, {/literal}{$LBL_OLD}{literal}: {{OldMessages}}</div>
    </a>
  </div>
  {{/if}}
</div>
{/literal}
</script>


<script type="text/x-handlebars" data-template-name="iptrunk">
<div style="float:left; border: black solid 0px;">
    <a class="pbxtooltip" href="#">
    <img src="modules/{$module_name}/images/info.png"/>
    <div>
        {literal}
        {{channel}} {{#if registered}}({{ip}}){{/if}}<br/>
        {{#if active}}
        <ul>
	    {{#each active}}
	    <li>{{formatSince}}: {{CallerIDNum}}</li>
	    {{/each}}
	    </ul>
	    {{/if}}
        {/literal}
    </div>
    </a>
</div>
<div style="float:left; width:115px; text-align:left; padding-left:4px;">
    {literal}
    <b>{{view.truncatedDescription}}</b><br/>
    {{#each active}}
    <span class="monitor">{{formatSince}}: {{CallerIDNum}}</span><br/>
    {{/each}}
    {/literal}
</div>
{literal}
<div style="float: right; border: black solid 0px;">
    <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/icon_trunk2.png"/>
</div>
{/literal}
</script>


<script type="text/x-handlebars" data-template-name="dahdispan">
<div style="float:left; border: black solid 0px;">
    <a class="pbxtooltip" href="#">
    <img src="modules/{$module_name}/images/info.png"/>
    <div>
        {literal}
        DAHDI/{{span}}: {{formatChanRange}}<br/>

        <!-- Mostrar los números de los canales en la alarma -->
        {{#each chan}}<span {{bindAttr style="alarmstyle"}}>{{chan}}</span>{{/each}}
        
        <!-- Mostrar las llamadas activas no clasificadas en un canal canal -->
        {{#if active}}
        <ul>
        {{#each active}}
        <li>{{formatSince}}: {{CallerIDNum}}</li>
        {{/each}}
        </ul>
        {{/if}}

        <!-- Mostrar las llamadas activas en cada canal -->
        <ul>
        {{#each chan}}
            {{#if active }}
	            <li>{{chan}}:
	            {{#each active}}
	                {{formatSince}}: {{CallerIDNum}}
	            {{else}}
	                (idle)
	            {{/each}}
	            </li>
            {{/if}}
        {{/each}}
        </ul>
        {/literal}
    </div>
    </a>
</div>
<div style="float:left; width:135px; text-align:left; padding-left:4px;">
    {literal}
    <b>DAHDI/{{span}}:</b> {{formatChanRange}}<br/>
    {/literal}
</div>
<div style="float: right; border: black solid 0px;">
    <img class="icon" src="modules/{$module_name}/images/icon_trunk2.png"/>
</div>
</script>

<script type="text/x-handlebars" data-template-name="queue">
    <div class="Queue-Container">
        {literal}
        <b>Queue: {{extension}} </b>({{view.truncatedDescription}})
        <i title="Reestablece los parámetros a 0" class="fa fa-refresh" style="cursor:pointer; font-size:25px; position:absolute; right:0; padding:2px" onclick="refreshQueueParameters(this.offsetParent.dataset.idattr)"></i>
        <div class="queueParameters">
        </div>
    </div>
    <div class="Queue-Container-Agent-Client">
        <div class="Queue-Agents" style="width:60%;"> 
            </div>
        <div style="width:40%;">
                <b>Customers in Queue:</b><span class="monitor"><b>{{callers.length}}</b></span><br/>
                {{#each callers}}<li><img src="modules/{/literal}{$module_name}{literal}/images/cliente.png" style="width: 15px; height: 15px;">{{CallerIDNum}}</li>{{/each}}
            {/literal}
        </div>
    </div>
</script>



<script type="text/x-handlebars" data-template-name="conference">
<div style="float:left; border: black solid 0px;">
	<a class="pbxtooltip" href="#">
	<img src="modules/{$module_name}/images/info.png"/>
	<div>
		{literal}
		{{extension}}: {{description}}<br/>
		{{#if callers}}
		{/literal}{$LBL_QUEUE_CALLERS}{literal}:<br/>
		<ul>
		{{#each callers}}
		<li>{{CallerIDName}} &lt;{{CallerIDNum}}&gt;</li>
		{{/each}}
		</ul>
		{{else}}
		{/literal}{$LBL_QUEUE_NO_CALLERS}{literal}
		{{/if}}
		{/literal}
	</div>
	</a>
</div>
<div style="float:left; width:135px; text-align:left; padding-left:4px;">
	{literal}
	<b>{{extension}}:</b> {{view.truncatedDescription}}<br/>
	{{#if callers }}
	<span class="monitor">{/literal}{$LBL_CONF_PARTICIPANTS}{literal}: {{callers.length}} - {{formatSince}}</span><br/>
	{{/if}}
	{/literal}
</div>
<div style="float: right; border: black solid 0px;">
    {literal}{{view App.DroppableIconView icon="{/literal}modules/{$module_name}/images/conference.png{literal}" }}{/literal}
</div>
</script>





<script type="text/x-handlebars" data-template-name="parkinglot">
<div style="float:left; border: black solid 0px;">
	<a class="pbxtooltip" href="#">
	<img src="modules/{$module_name}/images/info.png"/>
	<div>
		{literal}
		{{extension}}
		{/literal}
	</div>
	</a>
</div>
<div style="float:left; width:135px; text-align:left; padding-left:4px;">
	{literal}
	<b>{/literal}{$LBL_PARKED}{literal} ({{extension}})</b><br/>
	{{#if shortchannel }}
	<span class="monitor">{{shortchannel}}: {{formatTimeout}}</span><br/>
	{{/if}}
	{/literal}
</div>
<div style="float: right; border: black solid 0px;">
	{literal}{{view App.DroppableIconView icon="{/literal}modules/{$module_name}/images/parking.png{literal}" }}{/literal}
</div>
</script>



<script type="text/x-handlebars" data-template-name="editable-title">
{literal}
{{#if editing }}
{{view Ember.TextField valueBinding="description" }}
<button {{action "save" bubbles=false }}>{/literal}{$LBL_SAVE_NAME}{literal}</button>
{{else}}
{{description}} -- {{length}} ext
<span class="paneledittitle" {{action "edit" bubbles=false }}>[{/literal}{$LBL_EDIT_NAME}{literal}]</span>
{{/if}}
{/literal}
</script>
</div>

 

<script type="text/javascript">
var arrLang_main = {$ARRLANG_MAIN};
var var_init = {$VAR_INIT}

</script>

