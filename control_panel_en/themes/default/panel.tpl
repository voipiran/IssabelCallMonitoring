
<div id="controlPanelApplication">

<style type="text/css">
    @font-face { ... }

    /* ←←← فقط این خط جدید */
    .extensionView * { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }

    #controlPanelApplication * {
        font-family: 'IRANSans', Tahoma, sans-serif;
    }
</style>

<script type="text/x-handlebars" data-template-name="desktop">
    <div class="containerAll">
        <div class="statusbar">
            <!-- TODO: i18n -->
{literal}
{{#if connected}}
<div style="text-align: center; padding: 8px 0 10px; display: flex; justify-content: center; align-items: center; flex-direction: column;">
    <span style="color: #2e7d32; font-weight: bold; font-size: 14px; margin-bottom: 6px; position: absolute; left: 30px; top: 147px;">
        Connected
    </span>

        <div style="display: inline-flex; gap: 16px; align-items: center;">
            
            <!-- باکس فعال (سبز) -->
            <div style="
                min-width: 104px;
                height: 64px;
                background: #ffffff;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 0 14px;
                box-shadow: 0 3px 10px rgba(46,175,80,0.18);
                border-bottom: 5px solid #27ae60;
                font-weight: 600;
            ">
                <span style="font-size: 30px; color: #2e7d32; font-weight: 900;" class="Count-Registered"></span>
                <span style="color: #2e7d32; font-size: 12px; white-space: nowrap;">
                    Extensions Online
                </span>
            </div>

            <!-- باکس غیرفعال (قرمز) -->
            <div style="
                min-width: 104px;
                height: 64px;
                background: #ffffff;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 0 14px;
                box-shadow: 0 3px 10px rgba(244,67,54,0.18);
                border-bottom: 5px solid #e74c3c;
                font-weight: 600;
            ">
                <span style="font-size: 30px; color: #d32f2f; font-weight: 900;" class="Count-NotRegistered"></span>
                <span style="color: #c62828; font-size: 12px; white-space: nowrap;">
                    Extensions Offline
                </span>
            </div>

        </div>
    </div>
{{else}}
    <div style="text-align: left; padding: 12px 0;">
        <span style="color: #e53935; font-weight: bold; font-size: 14px; animation: pulse 1.8s infinite; position: absolute; left: 30px; top: 147px;">
              Connecting...
        </span>
    </div>
{{/if}}

<style>
@keyframes pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}
</style>
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
          {*  {literal}
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
            {/literal}</br> *}
        {* La lista de las extensiones asignadas al área 2 *}
             {*   {literal}
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
            {/literal}</br>  *}

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
</script><!-- data-template-name="desktop" -->


  {* باکس های داخلی صف *}
<script type="text/x-handlebars" data-template-name="extension">
<div style="float:left; border: black solid 0px; z-index:2;">
<a class="pbxtooltip" href="#">
     <img src="modules/{$module_name}/images/info.png"style="width:20px; height:20px; opacity:0.8;"/>
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
<div style="float:left; width:115px; text-align:left; padding-left:4px; position:relative; height:100%; ">
{literal}
<div style="float:left; text-align:left; font-weight:bold; white-space:nowrap; line-height:1.4;">
    <span style="color:#2c3e50;"><b>{{extension}}</b></span>
    {{#if view.truncatedDescription}}
        <span style="margin-left:7px; color:#34495e;"><b>{{view.truncatedDescription}}</b></span>
    {{/if}}
</div><br/>

<div class="line-monitor" style="position:absolute; right:8px; top:55%; transform:translateY(-50%); white-space:nowrap; font-size:12px; line-height:1.3; text-align:right; display:flex; flex-direction:column; align-items:flex-start;">
      <div><img src="modules/{/literal}{$module_name}{literal}/images/line.png" style="width:20px; height:20px; opacity:0.8;" /></div>
      <div><img src="modules/{/literal}{$module_name}{literal}/images/line.png" style="width:20px; height:20px; opacity:0.8;" /></div>
</div>



<div class="RemoteExtension" style="position:absolute; left:28px; right:auto; top:55%; transform:translateY(-50%); white-space:nowrap; font-size:12px; line-height:1.3; text-align:left; display:flex; flex-direction:column; align-items:flex-start; margin-top:18px;">
    {{#each active}}
        <div class="monitor" title="{{Channel}}" style="text-align:left;">{{formatSince}}: {{remoteExten}}</div>
    {{/each}}
</div>
{/literal}
</div>
{literal}
<div class="phone-mail-container" style="float:right; margin-left:8px; overflow:hidden;">
  <div class="phone-icon" style="float:left;">
    {{view App.DraggablePhoneIconView iconBinding="view.extensionIcon"}}
  </div>
  {{#if unreadMail }}
  <div class="mail-icon" style="float:left; margin-left:4px;">
    <a class="pbxtooltip">
      <img class="icon" src="modules/{/literal}{$module_name}{literal}/images/mail.png" style="width:23px; height:16px;" />
      <div>{/literal}{$LBL_NEW}{literal}: {{NewMessages}}, {/literal}{$LBL_OLD}{literal}: {{OldMessages}}</div>
    </a>
  </div>
  {{/if}}
</div>
{/literal}
{literal}<div style="clear:both;"></div>{/literal}
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
    <div class="Queue-Container" style="border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08); background:#fff; margin:8px 0;">
        {literal}

        <!-- هدر سبز ملایم و تمیز -->
<div style="background:#27ae60; color:white; padding:10px 16px; text-align:right; font-weight:600; position:relative;">
    Queue: {{extension}} 
    <span style="margin-right:8px; opacity:0.9; font-size:13px;">({{view.truncatedDescription}})</span>

    <i class="fa fa-refresh" title="بازنشانی آمار"
       style="position:absolute; right:14px; top:11px; font-size:20px; cursor:pointer;"
       onclick="refreshQueueParameters(this.offsetParent.offsetParent.dataset.idattr)"></i>
</div>


        <!-- آمار — ساده، تمیز و بزرگ -->
        <div class="queueParameters" style="padding:14px 16px; background:#f9fdfa; display:flex; justify-content:space-around; border-bottom:1px solid #e8f5e9; font-size:14px;">
            <div style="text-align:center;">
                <div style="font-size:28px; font-weight:bold; color:#27ae60;">{{answered}}</div>
                <div style="color:#16a085; margin-top:4px;">Answered: </div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:28px; font-weight:bold; color:#e74c3c;">{{abandoned}}</div>
                <div style="color:#c0392b; margin-top:4px;">Abandoned: </div>
            </div>
        </div>

        <!-- اپراتورها و مشتریان — کاملاً تمیز و مرتب -->
        <div class="Queue-Container-Agent-Client" style="display:flex;">
            <div class="Queue-Agents" style="width:60%; background:#f9fdfa; padding:12px 14px;">
                <div style="background:#e8f7ee; color:#27ae60; font-weight:bold; text-align:center; padding:6px 10px; border-radius:6px; margin-bottom:8px; font-size:13px;">
                    Agents
                </div>
                {{#each agents}}
                    {{view App.AgentView}}
                {{/each}}
            </div>

            <div style="width:40%; background:#ffffff; padding:12px 10px;">
                <div style="background:#e3f2fd; color:#2980b9; font-weight:bold; text-align:center; padding:6px 10px; border-radius:6px; margin-bottom:8px; font-size:13px;">
                    Customers in Queue: <b style="color:#e67e22;">{{callers.length}}</b>
                </div>
                <div style="max-height:130px; overflow-y:auto;">
                    {{#each callers}}
                        <div style="background:#f8f9fa; padding:5px 8px; margin:4px 0; border-radius:5px; text-align:center; font-size:12.5px;">
                            <img src="modules/{/literal}{$module_name}{literal}/images/clientes.png" style="width:14px; height:14px; vertical-align:middle; margin-left:5px;">
                            {{CallerIDNum}}
                        </div>
                    {{/each}}
                </div>
            </div>
        </div>

        {/literal}
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

<!-- لینک VoipIran - مینیمال و هماهنگ با تم Issabel -->
<div style="margin: 20px 10px 10px; padding: 14px 18px; background: #f8fff9; border: 1px solid #e0e8e2; border-left: 4px solid #27ae60; border-radius: 10px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
    <a href="https://voipiran.io" target="_blank" rel="noopener" style="text-decoration: none; color: #2c3e50; font-size: 15px; font-weight: 500; display: inline-flex; align-items: center; gap: 12px; transition: color 0.2s;">
        <img src="modules/control_panel/images/voipiran.png" 
             alt="VoipIran" 
             loading="lazy"
             style="width: auto; height: auto; max-width: 110px; max-height: 44px; border-radius: 6px;">
        <span style="color: #27ae60;">توسعه‌یافته توسط VoipIran.io</span>
    </a>
</div>

<script type="text/javascript">
var arrLang_main = {$ARRLANG_MAIN};
var var_init = {$VAR_INIT}
</script>

