// https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/Function/bind
if (!Function.prototype.bind) {
	Function.prototype.bind = function (oThis) {
		if (typeof this !== "function") {
			// closest thing possible to the ECMAScript 5 internal IsCallable function
			throw new TypeError("Function.prototype.bind - what is trying to be bound is not callable");
		}

		var aArgs = Array.prototype.slice.call(arguments, 1), 
        	fToBind = this, 
        	fNOP = function () {},
        	fBound = function () {
        		return fToBind.apply(this instanceof fNOP && oThis
                                 ? this
                                 : oThis,
                               aArgs.concat(Array.prototype.slice.call(arguments)));
        };

        fNOP.prototype = this.prototype;
        fBound.prototype = new fNOP();

        return fBound;
	};
}


// IE6 e IE8 funcionan incorrectamente al anidar draggables
// http://bugs.jqueryui.com/ticket/4333
$.extend($.ui.draggable.prototype, (function (orig) {
          return {
            _mouseCapture: function (event) {
              var result = orig.call(this, event);
              if (result && $.browser.msie) event.stopPropagation();
              return result;
            }
          };
})($.ui.draggable.prototype["_mouseCapture"]));


var module_name = 'control_panel';
var App = null;

//Redireccionar la página entera en caso de que la sesión se haya perdido
function verificar_error_session(respuesta)
{
	if (respuesta['statusResponse'] == 'ERROR_SESSION') {
		if (respuesta['error'] != null && respuesta['error'] != '')
			alert(respuesta['error']);
		window.open('index.php', '_self');
	}
}

$(document).ready(function() {
	// Inicialización de Ember.js
	App = Ember.Application.create({
		/*
		LOG_TRANSITIONS: true,
		LOG_ACTIVE_GENERATION: true,
		LOG_VIEW_LOOKUPS: true,
		*/
		rootElement:	'#controlPanelApplication'
	});
	initializeJQueryUIViews();
	/* Este objeto contiene un timer regular que actualiza la propiedad tick500
	 * cada 500 milisegundos. Si algún objeto tiene una propiedad computada que
	 * observa la propiedad tick500, tendrá la oportunidad de actualizar un
	 * contador de tiempo. */
	App.Ticks = Ember.Object.extend({
		serverdiff: null,	// Diferencia entre servidor y cliente
		timer: null,
		
		// La propiedad que actualiza la estimación de la hora del SERVIDOR
		tick500: null,
		
		// Iniciar la estimación de la hora del SERVIDOR
		initTick: function(v) {
			if (this.get('tick500') == null) this.set('tick500', v);
			
			var ahora = new Date();
			var d = new Date();
			d.setTime(v * 1000 - ahora.getTime());
			this.set('serverdiff', d);
						
			if (this.timer != null) clearInterval(this.timer);
			this.timer = setInterval(this.updateTick.bind(this), 500);
		},
		
		updateTick: function() {
			var ahora = new Date();
			this.set('tick500', ahora.getTime() + this.get('serverdiff').getTime());
		}
	});
	App.ticks = App.Ticks.create({});
	App.ticks.initTick(var_init['TIMESTAMP_START']);
	
	/* Este objeto representa un canal activo dentro de una extensión o troncal */
	App.ActiveChannel = Ember.Object.extend({
		Channel:			null,
		CallerIDNum:		null,
		CallerIDName:		null,
		Since:				null,
		BridgedChannel:		null,
		ConnectedLineNum:	null,
		ConnectedLineName:	null,
		ChannelStateDesc:	null,
		Context:			null,
		Extension:			null,
		Priority:			null,
		Application:		null,
		AppData:			null,
		
		remoteExten: function() {
			var r;
			r = this.get('Extension');
			if (r != null) return r;
			return this.get('ConnectedLineNum');
		}.property('Extension', 'ConnectedLineNum'),
		formatSince: function() {
			var since = this.get('Since');
			//console.log(since);
			if (since == null) return '???';
			return formatoMilisegundo((App.ticks.get('tick500') - since * 1000));
		}.property('App.ticks.tick500')
	});
	
	/* Este objeto representa una extensión telefónica local al servidor */
	App.Extension = Ember.Object.extend({
		channel:		null,
		tech:			null,
		extension:		null,
		description:	null,
		current_area:	'Extension',	// Extension,Area1,Area2,Area3
		mailbox:		null,
		UrgMessages:	0,
		NewMessages:	0,
		OldMessages:	0,
		ip:				null,
		registered:		false,
		active:			null,
		
		init: function() {
			var active = this.get('active');
			if (active == null) active = [];
			this.setActive(active);
		},
		idattr: function() {
			return this.get('channel');
		}.property('channel'),
		
		setActive: function(active) {
    //console.log(active);
    for (var i = 0; i < active.length; i++) {
        active[i] = App.ActiveChannel.create(active[i]);
    }
    this.set('active', active);
},

		// Estado de la extensión o troncal: Up|Ringing|Down
		trunkstate: function () {
			var active = this.get('active');
			for (var i = 0; i < active.length; i++) {
				var st = active[i].get('ChannelStateDesc');
				if (st == 'Up') return 'Up';
				if (st == 'Ringing') return 'Ringing';
			}
			return 'Down';
		}.property('active'),
		
		// Bandera que indica si hay nuevos mensajes
		unreadMail: function() {
			return (this.get('NewMessages') > 0);
		}.property('NewMessages'),
		
		// Observador que guarda el área asignada a esta extensión
		saveArea: function() {
			$.post('index.php?menu=' + module_name + '&rawmode=yes', {
				menu:		module_name, 
				rawmode:	'yes',
				action:		'updateExtensionPanel',
				panel:		this.get('current_area'),
				extension:	this.get('extension')
			},
			function(respuesta) {
				verificar_error_session(respuesta);
				if (respuesta.status == 'error') {
					alert(respuesta.message);
				}
			}.bind(this), 'json');
		}.observes('current_area')
	});
	
	/* Este objeto representa una troncal IP */
	App.IPTrunk = Ember.Object.extend({
		channel:		null,
		tech:			null,
		description: function () {
			return this.get('channel');
		}.property('channel'),
		ip:				null,
		registered:		false,
		active:			null,
		
		init: function() {
			var active = this.get('active');
			if (active == null) active = [];
			this.setActive(active);
		},
		idattr: function() {
			return this.get('channel');
		}.property('channel'),
		// Actualizar los canales activos según la lista indicada
		setActive: function(active) {
			for (var i = 0; i < active.length; i++)
				active[i] = App.ActiveChannel.create(active[i]);
			this.set('active', active);
		}
	});
	
	/* Este objeto representa una sola línea física de un span DAHDI */
	App.DAHDIChan = Ember.Object.extend({
		chan:		null,	// Número de la línea física
		active:		null,	// Lista de canales no asociados con una línea física
		Alarm:		null,	// Alarma asociada al canal
		init: function() {
			var active = this.get('active');
			if (active == null) active = [];
			this.setActive(active);
		},
		// Actualizar los canales activos según la lista indicada
		setActive: function(active) {
			for (var i = 0; i < active.length; i++)
				active[i] = App.ActiveChannel.create(active[i]);
			this.set('active', active);
		},
		alarmstyle: function () {
			var style = 'display: inline-block; min-width: 10px; padding: 2px;';
			switch (this.get('Alarm')) {
			case 'No Alarm':		style += 'background-color: green;'; break;
			case 'Red Alarm':		style += 'background-color: red;'; break;
			case 'Yellow Alarm':	style += 'background-color: yellow;'; break;
			case 'Blue Alarm':		style += 'background-color: blue;'; break;
			case 'Recovering':		style += 'background-color: orange;'; break;
			case 'Loopback':		style += 'background-color: purple;'; break;
			case 'Not Open':		style += 'background-color: silver;'; break;
			case 'None':			style += 'background-color: black;'; break;
			}
			return style;
		}.property('Alarm')
	});
	
	/* Este objeto representa un span DAHDI que contiene líneas telefónicas */
	App.DAHDISpan = Ember.Object.extend({
		span:		null,	// Número del span
		active:		null,	// Lista de canales no asociados con una línea física
		chan:		null,	// Lista de líneas físicas
		
		init: function() {
			var active = this.get('active');
			if (active == null) active = [];
			this.setActive(active);
			
			var chan = this.get('chan');
			if (chan == null) chan = [];
			this.setChan(chan);
		},
		idattr: function() {
			return 'DAHDI/' + this.get('span');
		}.property('span'),
		// Actualizar los canales activos según la lista indicada
		setActive: function(active) {
			for (var i = 0; i < active.length; i++)
				active[i] = App.ActiveChannel.create(active[i]);
			this.set('active', active);
		},
		setChan: function (chan) {
			for (var i = 0; i < chan.length; i++)
				chan[i] = App.DAHDIChan.create(chan[i]);
			this.set('chan', chan);
		},
		formatChanRange: function() {
			var chanlist = this.get('chan').mapBy('chan');
			var spanstart = null;
			var spanend = null;
			var spandesc = '';
			
			for (var i = 0; i < chanlist.length; i++) {
				if (spanstart == null) {
					spanstart = spanend = chanlist[i];
				} else if (spanend + 1 == chanlist[i]) {
					spanend = chanlist[i];
				} else {
					if (spandesc != '') spandesc += ',';
					spandesc += spanstart + '-' + spanend;
					spanstart = spanend = chanlist[i];
				}
			}
			if (spandesc != '') spandesc += ',';
			spandesc += spanstart + '-' + spanend;
			return spandesc;
		}.property('chan.@each')
	});
	
	/* Este objeto representa un miembro de una cola */
	App.QueueMember = Ember.Object.extend({
		fullchannel: null,
		shortchannel: function() {
			var regexp = /^.+?\/(\d+)/;
			var regs = regexp.exec(this.get('fullchannel'));
			return (regs != null) ? regs[1] : this.get('fullchannel');
		}.property('fullchannel')
	});
	App.Queue = Ember.Object.extend({
		extension:		null,
		description:	null,
		members:		null,
		callers:		null,
		
		init: function() {
			if (this.get('callers') == null) this.set('callers', []);
			var members = this.get('members');
			if (members == null) members = [];
			this.setMembers(members);
		},
		idattr: function() {
			return 'QUEUE/' + this.get('extension');
		}.property('extension'),
	
		setMembers: function (members) {
			//console.log(members)
			for (var i = 0; i < members.length; i++)
				members[i] = App.QueueMember.create({
					fullchannel: members[i]
				});
			this.set('members', members);
		}
	});


	App.Conference = Ember.Object.extend({
		extension:		null,
		description:	null,
		callers:		null,
		Since:			null,

		init: function() {
			if (this.get('callers') == null) this.set('callers', []);
		},
		idattr: function() {
			return 'CONFERENCE/' + this.get('extension');
		}.property('extension'),
		updateConfStart: function() {
			var callers = this.get('callers');
			if (callers.length <= 0) {
				this.set('Since', null);
				return;
			}
			var since = this.get('Since');
			for (var i = 0; i < callers.length; i++) {
				if (since == null || callers[i].ConfSince < since) {
					since = callers[i].ConfSince;
				}
			}
			this.set('Since', since);
		}.observes('callers.@each'),
		formatSince: function() {
			var since = this.get('Since');
			if (since == null) return '???';
			return formatoMilisegundo((App.ticks.get('tick500') - since * 1000));
		}.property('Since', 'App.ticks.tick500')
	});
	
	App.Parkinglot = Ember.Object.extend({
		extension:	null,
		Channel:	null,
		Since:		null,
		Timeout:	null,
		
		idattr: function() {
			return 'PARKLOT/' + this.get('extension');
		}.property('extension'),
		shortchannel: function() {
			var regexp = /^.+?\/(\d+)/;
			var regs = regexp.exec(this.get('Channel'));
			return (regs != null) ? regs[1] : this.get('Channel');
		}.property('Channel'),
		formatTimeout: function() {
			var since = this.get('Since');
			var timeout = this.get('Timeout');
			if (since == null || timeout == null) return '???';
			return formatoMilisegundo(((since + timeout) * 1000 - App.ticks.get('tick500')));
		}.property('Since', 'Timeout', 'App.ticks.tick500')
	});
	
	/* El siguiente controlador contiene una lista de objetos que se actualizan
	 * dentro de un panel. Estos objetos que se actualizan pueden ser 
	 * extensiones, troncales, colas, o conferencias. */
	App.PBXPanelController = Ember.ArrayController.extend({
		name:				null,
		width:				null,
		height:				null,
		color:				null,
		description:		null,
		typedclass:			null,	// Clase para inicializar hijos
		parentcontroller:	null,	// Controlador del desktop
		finishedloading:	false,
		
		editing: false,
		
		actions: {
			edit: function () {
				this.set('editing', true);
			},
			save: function () {
				$.post('index.php?menu=' + module_name + '&rawmode=yes', {
					menu:		module_name, 
					rawmode:	'yes',
					action:		'updatePanelDesc',
					panel:		this.get('name'),
					description:this.get('description')
				},
				function(respuesta) {
					verificar_error_session(respuesta);
					if (respuesta.status == 'error') {
						alert(respuesta.message);
					}
				}.bind(this), 'json');
				this.set('editing', false);
			},
			dialvoicemail: function(sourceExtension) {
				$.post('index.php?menu=' + module_name + '&rawmode=yes', {
					menu:		module_name, 
					rawmode:	'yes',
					action:		'voicemailExtension',
					source:		sourceExtension.get('extension')
				},
				function(respuesta) {
					verificar_error_session(respuesta);
					if (respuesta.status == 'error') {
						alert(respuesta.message);
					}
				}.bind(this), 'json');
			}
		},
		findIndexBy: function(key, value) {
			for (var i = 0; i < this.get('length'); i++) {
				if (this.objectAt(i).get(key) == value) {
					return i;
				}
			}
			return null;
		},
		addTypedObject: function(obj) {
			var typedclass = this.get('typedclass');
			if (typedclass == null) {
				console.debug('Uninitialized typedclass');
				console.debug(obj);
				return;
			}
			this.addObject(typedclass.create(obj));
		}
	});

	/* La siguiente vista es la vista base que se usa para representar un panel. */
	App.PBXPanelView = Ember.View.extend(JQ.Widget, {
		uiType: ['resizable'],
		uiOptions: ['autoHide', 'minWidth', 'minHeight'],
		uiEvents: ['stop'],
		tagName: 'li',
        autoHide: true,	// Esconder manijas de redimensionamiento
        minWidth: 380,
        minHeight: 100,
		
		stop: function (event, ui) {
			var controller = this.get('controller');
			var desktopController = this.get('parentView.controller');
			desktopController.updatePanelSize(controller.get('name'), ui.size.width, ui.size.height);
		},
     
		classNames: ['pbxpanel'],
		attributeBindings: ['style'],
		style: function() {
			var s = '';
			//if (this.get('controller.width') != null) s += 'width: ' + this.get('controller.width') + 'px; ';
			if (this.get('controller.height') != null) s += 'height: ' + this.get('controller.height') + 'px; ';
			//if (this.get('controller.color') != null) s += 'background-color: ' + this.get('controller.color') + '; ';
			return s;
		}.property('controller.width', 'controller.height', 'controller.color')
	});
	App.EditableTitleView = Ember.View.extend({
		tagName: 'dt',
		templateName: 'editable-title'
	});
	App.SortablePanelView = Ember.View.extend(JQ.Widget, {
		uiType:			'sortable',
		uiOptions:		['connectWith', 'revert', 'cursor'],
		uiEvents:		['receive', 'update'],
		classNames:		['sortablepanelview'],
		connectWith:	'.sortablepanelview',
		revert:			true,
		cursor:			'crosshair',
		
		// Obtener índice donde colocar el item, o null si se coloca al final
		getInsertPos: function(item) {
			var controller = this.get('controller');
			var insertpos = null;
			var place_id = item.nextAll('div').first().attr('data-idattr');
			
			if (place_id != null) {
				insertpos = controller.findIndexBy('idattr', place_id);
			}
			return insertpos;
		},
		
		update: function (event, ui) {
			var sel = '#' + ui.item.attr('id');
			var div_set = this.get('ui').children(sel);
			
			/* El evento update se ejecuta para todo cambio de DOM en la lista,
			 * pero el item insertado es localizable sólo cuando se arrastra
			 * desde la misma lista. Por lo tanto, div_set tendrá contenido sólo
			 * si el elemento arrastrado procede de la misma lista.
			 * 
			 * Para la lista de elementos de PBX, se debe buscar la posición
			 * adecuada en la lista para el cambio de posición. Luego se cancela
			 * el movimiento por ratón, y se repite el movimiento a través de
			 * los controladores.
			 * 
			 * Si div_set.length es 0, entonces la actualización corresponde a
			 * un arrastre entre dos paneles, y esto se maneja en el evento
			 * receive().
			 */
			if (div_set.length > 0) {
				var controller = this.get('controller');
				
				// Véase comentario de orden de operaciones en receive()
				var dropped_id = ui.item.attr('data-idattr');
				var removepos = null;
				var insertpos = this.getInsertPos(div_set);

				removepos = controller.findIndexBy('idattr', dropped_id);

				this.get('ui').sortable('cancel');

				if (removepos == null) {
					console.error('BUG: failed to find extension for ' + dropped_id + ' in controller!');
					return;
				}
				
				// Se repite el cambio de posición a través del controlador.
				this.moveExtension(controller, removepos, controller, insertpos);
			}
		},
		receive: function (event, ui) {
			var dropped_id = ui.item.attr('data-idattr');
			var dstController = this.get('controller');			

			// Localizar el DesktopController
			var parentController = dstController.get('parentcontroller');
			
			// Localizar la extensión que debe de quitarse
			var r = parentController.localizarControladorExtension('idattr', dropped_id);
			var srcController = null;
			if (r != null) {
				srcController = r[0];
				var removepos = r[1];
			}
			if (srcController == null) {
				console.error('BUG: failed to fetch srcController for extension!');
				ui.sender.sortable('cancel'); return;
			}
			
			/* El orden de operaciones es importante. Primero hay que calcular
			 * las posiciones fuente y destino de la extensión a mover, SIN
			 * MODIFICAR LOS CONTROLADORES. Luego de aprobar el movimiento
			 * de la extensión, y sabiendo la posición de inserción, se cancela
			 * el drag, y a continuación se modifican los controladores para
			 * que estos sean los que modifiquen el UI. 
			 */

			// Agregar la extensión en la posición indicada por el drop
			var insertpos = this.getInsertPos(ui.item);
			
			ui.sender.sortable('cancel');
			var extension = this.moveExtension(srcController, removepos, dstController, insertpos);
			extension.set('current_area', dstController.get('name'));
		},
		moveExtension: function(srcController, removepos, dstController, insertpos) {
			var extension = srcController.objectAt(removepos);
			srcController.removeAt(removepos);
			if (insertpos != null) {
				if (srcController == dstController && removepos < insertpos) {
					insertpos--;
				}
				dstController.insertAt(insertpos, extension);
			} else {
				dstController.addObject(extension);
			}
			return extension;
		}
	});

	// La siguiente vista contiene elementos que se reordenan
	App.BaseSortableView = Ember.View.extend(JQ.Widget, {
		uiType: ['sortable'],
		//tagName: 'ul',
		classNames: ['lisortable'],
		uiOptions: ['opacity', 'cursor'],
		cursor: 'move'
	});
	
	// La siguiente vista tiene además de reordenamiento, aplicado jquery.faq
	App.FAQView = App.BaseSortableView.extend({
		tagName: 'dl',
		didInsertElement: function () {
			var ui = this.$();
			ui.faq();
		}
	});
	
	/* La siguiente vista es la vista base que se usa para representar una 
	 * extensión. En esta vista, y en todas las otras vistas que representan
	 * un solo element, el valor de context es la extensión/cola/otro elemento.
	 */
	App.PBXElementView = Ember.View.extend({
		classNames: ['pbxelement'],
		classNameBindings: ['context.registered:pbxactive'],
		attributeBindings: ['dataIdattr:data-idattr'],
		dataIdattr: function() {
			return this.get('context.idattr');
		}.property('context.idattr'),
		truncatedDescription: function() {
			var s = this.get('context.description');
			if (s.length <= 15) return s;
			return s.substr(0, 15) + '...';
		}.property('context.description')
	});
	App.ExtensionView = App.PBXElementView.extend({
		classNames: ['ExtensionStyle'],
		templateName: 'extension',
		click: function(event) {
    // Obtener los atributos de la vista
    const attributes = event.currentTarget.attributes;
    // Llamar a la función selectExtension con los atributos
    const monitorElements = event.target.querySelectorAll('.monitor');
    const titleData = {};
    
	    // Itera sobre los elementos encontrados y agrega los valores al objeto
	    monitorElements.forEach(function(monitorElement, index) {
      // Obtiene el valor del atributo 'title' de cada elemento
      const title = monitorElement.getAttribute('title');
      // Asigna el valor al objeto usando un nombre de propiedad basado en el índice
      titleData[`title${index + 1}`] = title;
    });
    // Muestra el objeto con los valores en la consola
    selectExtension(attributes, titleData);
	},
		// Elegir un icono de telefono según el estado de la extensión
		extensionIcon: function() {
			var st = this.get('context.trunkstate');
			var iconpath = 'modules/' + module_name + '/images/';
			if (st == 'Up')
				return iconpath + 'icon_upPhone.png';
			else if (st == 'Ringing')
				return iconpath + 'phoneRinging.gif';
			else
				return iconpath + 'phhonez0.png';
		}.property('context.trunkstate')
	});
	App.QueueView = App.PBXElementView.extend({
		classNames: ['pbxelement', 'pbxactive'],
		templateName: 'queue'
	});
	App.ConferenceView = App.PBXElementView.extend({
		classNames: ['pbxelement', 'pbxactive'],
		templateName: 'conference'
	});
	App.ParkinglotView = App.PBXElementView.extend({
		classNames: ['pbxelement', 'pbxactive'],
		templateName: 'parkinglot'
	});
	App.IPTrunkView = App.PBXElementView.extend({
		templateName: 'iptrunk'
	});
	App.DAHDISpanView = App.PBXElementView.extend({
		classNames: ['pbxelement', 'pbxactive'],
		templateName: 'dahdispan'
	});
	
	/* La siguiente vista representa un icono que puede recibir un teléfono
	 * arrastrado para realizar una llamada hacia la extensión que posee la
	 * vista. */
	App.DroppableIconView = Ember.View.extend(JQ.Widget, {
		uiType:				['droppable'],
		uiEvents:			['drop'],
		tagName:			'img',
		classNames:			['icon'],
		attributeBindings:	['icon:src'],
		icon: null,	// <-- seteado en la plantilla con cada icono requerido
		
		drop: function(event, ui) {
			if (ui == null) return;

			var targetExtension = this.get('context');
			var dropped_id = ui.draggable.parents('.pbxelement').attr('data-idattr');

			var dstController = this.get('controller');			

			// Localizar el DesktopController
			var parentController = dstController.get('parentcontroller');
			
			// Localizar el controlador que provee la extensión
			var r = parentController.localizarControladorExtension('idattr', dropped_id);
			var srcController = r[0];
			var extpos = r[1];
			if (srcController == null) {
				console.error('BUG: failed to fetch srcController for extension!');
				return;
			}
			var sourceExtension = srcController.objectAt(extpos);

			$.post('index.php?menu=' + module_name + '&rawmode=yes', {
				menu:		module_name, 
				rawmode:	'yes',
				action:		'callExtension',
				source:		sourceExtension.get('extension'),
				target:		targetExtension.get('extension')
			},
			function(respuesta) {
				verificar_error_session(respuesta);
				/*
				if (respuesta.status == 'error') {
					mostrar_mensaje_error(respuesta.message);
				}
				*/
				console.debug(respuesta);
			}.bind(this), 'json');
		}
	});
	
	/* La siguiente vista representa un icono de teléfono que puede ser 
	 * arrastrado además de recibir arrastres. */
	App.DraggablePhoneIconView = App.DroppableIconView.extend({
		uiType:		['droppable', 'draggable'],
		uiOptions:	['revert', 'cursor', 'zIndex'],
		revert:		true,
		cursor:		'crosshair',
		zIndex:		990,
		
		doubleClick: function (event) {
			var targetExtension = this.get('context');
			$.post('index.php?menu=' + module_name + '&rawmode=yes', {
				menu:		module_name, 
				rawmode:	'yes',
				//action:		'hangupExtension',
				target:		targetExtension.get('extension')

			},
			function(respuesta) {
				verificar_error_session(respuesta);
				if (respuesta.status == 'error') {
					alert(respuesta.message);
				}
			}.bind(this), 'json');
		}
	});
	
	/* El siguiente controlador es el controlador de más alto nivel. Este 
	 * controlador modela el escritorio que se muestra en la pantalla. El 
	 * escritorio contiene paneles (PBXPanelController) que a su vez contienen 
	 * objetos que se actualizan. Este controlador administra las llamadas
	 * AJAX que actualizan en tiempo real los objetos. */
	App.DesktopController = Ember.ObjectController.extend({
		// Los siguientes controladores son las áreas a mostrar
		extensions:			null,
		area1: 				null,
		area2: 				null,
		area3: 				null,
		queues:				null,
		dahdi:				null,
		iptrunks: 			null,
		conferences:		null,
		parkinglots:		null,
		longPoll:			null,	// Objeto de POST largo
		evtSource:			null,	// Objeto EventSource, si está soportado por el navegador
		estadoClienteHash: 	var_init['ESTADO_CLIENTE_HASH'],	// Hash del estado del cliente
		connected:			false,

		init: function() {
			for (var k in var_init['ESTADO_PANELES']) {
				var_init['ESTADO_PANELES'][k]['parentcontroller'] = this;
			}
			var_init['ESTADO_PANELES']['Extension']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area1']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area2']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area3']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area4']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area5']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area6']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area7']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area8']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Area9']['typedclass'] = App.Extension;
			var_init['ESTADO_PANELES']['Queues']['typedclass'] = App.Queue;
			var_init['ESTADO_PANELES']['Conferences']['typedclass'] = App.Conference;
			var_init['ESTADO_PANELES']['Parkinglots']['typedclass'] = App.Parkinglot;
			var_init['ESTADO_PANELES']['TrunksSIP']['typedclass'] = App.IPTrunk;
			var_init['ESTADO_PANELES']['Trunks']['typedclass'] = App.DAHDISpan;
			
			this.extensions = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Extension']);
			this.area1 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area1']);
			this.area2 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area2']);
			this.area3 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area3']);
			this.area4 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area4']);
			this.area5 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area5']);
			this.area6 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area6']);
			this.area7 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area7']);
			this.area8 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area8']);
			this.area9 = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Area9']);
			this.queues = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Queues']);
			this.conferences = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Conferences']);
			this.parkinglots = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Parkinglots']);
			this.dahdi = App.PBXPanelController.create(var_init['ESTADO_PANELES']['Trunks']);
			this.iptrunks = App.PBXPanelController.create(var_init['ESTADO_PANELES']['TrunksSIP']);
			
			setTimeout(this.pbxStatus.bind(this), 1);
			
			// Esta variable puede usarse para localizar el resto de objetos en debug
			debug_root = this;
		},
		
		pbxStatus: function() {
			var params = {
					menu:		module_name, 
					rawmode:	'yes',
					clientstatehash: this.get('estadoClienteHash'),
					action:		'pbxStatus'
				};

			if (window.EventSource) {
				params['serverevents'] = true;
				this.evtSource = new EventSource('index.php?' + $.param(params));
				this.evtSource.onmessage = function(event) {
					this.set('connected', true);
					this.manejarRespuestaStatus($.parseJSON(event.data));
				}.bind(this);
				this.evtSource.onerror = function(event) {
					this.set('connected', false);
				}.bind(this);
			} else {
				this.longPoll = $.get('index.php', params,
				function (respuesta) {
					verificar_error_session(respuesta);
					this.set('connected', true);
					if (this.manejarRespuestaStatus(respuesta)) {
						// Lanzar el método de inmediato
						setTimeout(this.pbxStatus.bind(this), 1);
					}
				}.bind(this), 'json');
			}
			
			// Apagar el SSE al cerrar la ventana
			$(window).unload(this.pbxStatus_shutdown.bind(this));
		},
		
		pbxStatus_shutdown: function () {
			if (this.evtSource != null) {
				this.evtSource.onmessage = function(event) {
					//console.warn("This evtSource was closed but still receives messages!");
				}
				this.evtSource.onerror = null;
				this.evtSource.close();
				this.evtSource = null;
			}
			if (this.longPoll != null) {
				this.longPoll.abort();
				this.longPoll = null;
			}
			
			$.post('index.php?menu=' + module_name + '&rawmode=yes', {
				menu:		module_name, 
				rawmode:	'yes',
				action:		'pbxStatusShutdown'
			},
			function(respuesta) {
				verificar_error_session(respuesta);
				console.debug(respuesta);
			}.bind(this), 'json');
		},
		
		localizarControladorExtension: function(key, value) {
			var controller = null;
			var objpos = null;
			var controllerList = ['extensions', 'area1', 'area2', 'area3', 'area4', 'area5', 'area6', 'area7', 'area8', 'area9'];
			for (var i = 0; i < controllerList.length; i++) {
				var c = this.get(controllerList[i]);
				if (c == null) return null;
				objpos = c.findIndexBy(key, value);
				if (objpos != null) {
					controller = c;
					break;
				}
			}
			return (objpos != null) ? [controller, objpos] : null;
		},
		
		manejarRespuestaStatus: function(respuesta) {
			//console.debug(respuesta);

			// Intentar recargar la página en caso de error
			if (respuesta.error != null) {
				window.alert(respuesta.error);
				location.reload();
				return false;
			}

			if (respuesta.timestamp != null) {
				App.ticks.initTick(respuesta.timestamp);
			}
			
			if (respuesta.estadoClienteHash == 'mismatch') {
				/* Ha ocurrido un error y se ha perdido sincronía. Si el hash que 
				 * recibió es distinto a estadoClienteHash entonces esta es una petición
				 * vieja. Si es idéntico debe de recargase la página.
				 */
				/*
				if (respuesta.hashRecibido == this.get('estadoClienteHash')) {
					// Realmente se ha perdido sincronía
					//console.error("Lost synchronization with server, reloading page...");
					location.reload();
				} else {
					// Se ha recibido respuesta luego de que supuestamente se ha parado
					//console.warn("Received mismatch from stale SSE session, ignoring...");
				}
				*/
				location.reload();
				return false;
			}
			this.set('estadoClienteHash', respuesta.estadoClienteHash);
			
			// Manejar cada objeto a medida que llega.
			for (var i = 0; i < respuesta.pbxchanges.length; i++) {
				//console.log(respuesta);
				obj = respuesta.pbxchanges[i];
				switch (obj.changetype) {
				case 'create':
					refreshQueues();
					if(obj.objtype === 'phones'){
						contarPhones(obj);
					}
					this.insertPBXObject(obj);
					break;
				case 'update':
					this.updatePBXObject(obj);
					break;
				case 'delete':
					this.deletePBXObject(obj);
					break;
				default:
					console.debug('Unimplemented changetype: ' + obj.changetype);
					break;
				}
			}
			
			// Indicar a todos los controladores que se terminó la carga
			var controlkeys = ['extensions', 'area1', 'area2', 'area3', 'area4', 'area5', 'area6', 'area7', 'area8', 'area9','queues',
			                   'conferences', 'parkinglots', 'dahdi', 'iptrunks'];
			for (var i = 0; i < controlkeys.length; i++) {
				this.get(controlkeys[i]).set('finishedloading', true);
			}
			
			return true;
		},
		
		locateTargetController: function (obj) {
			var controller;
			
			switch (obj.objtype) {
			case 'phones':
				controller = this.get('extensions');
				switch (obj.current_area) {
				case 'Area1': controller = this.get('area1'); break;
				case 'Area2': controller = this.get('area2'); break;
				case 'Area3': controller = this.get('area3'); break;
				case 'Area4': controller = this.get('area4'); break;
				case 'Area5': controller = this.get('area5'); break;
				case 'Area6': controller = this.get('area6'); break;
				case 'Area7': controller = this.get('area7'); break;
				case 'Area8': controller = this.get('area8'); break;
				case 'Area9': controller = this.get('area9'); break;
				}
				break;
			case 'conferences':
			case 'parkinglots':
			case 'queues':
			case 'iptrunks':
			case 'dahdi':
				controller = this.get(obj.objtype); break;
			default:
				console.debug('Unimplemented type: ' + obj.objtype)
				return null;
			}
			return controller;
		},
		
		insertPBXObject: function(obj) {
		  var controller = this.locateTargetController(obj);
		  if (controller != null) controller.addTypedObject(obj);
		},
		
		updatePBXObject: function(obj) {
			var dstController = this.locateTargetController(obj);
			var pbxobject;
			//console.log(obj);
			switch (obj.objtype) {
			case 'phones':
				var r = this.localizarControladorExtension('channel', obj.channel);
				var srcController = r[0];
				var extpos = r[1];
				if (srcController == null) {
					console.error('BUG: failed to fetch srcController for extension!');
					return;
				}
				pbxobject = srcController.objectAt(extpos);
				if (pbxobject.get('current_area') != obj.current_area) {
					// Cambiar de área el objeto
					srcController.removeAt(extpos);
					dstController.addTypedObject(obj);
					return;
				}
				break;
			case 'conferences':
			case 'parkinglots':
			case 'queues':
				var f = dstController.filterBy('extension', obj.extension);
				if (f.length > 0) pbxobject = f[0];
				break;
			case 'iptrunks':
				var f = dstController.filterBy('channel', obj.channel);
				if (f.length > 0) pbxobject = f[0];
				break;
			case 'dahdi':
				var f = dstController.filterBy('span', obj.span);
				if (f.length > 0) pbxobject = f[0];
				break;
			default:
				console.debug('Unimplemented type: ' + obj.objtype)
				break;
			}
			if (pbxobject != null) {
				for (var k in obj) switch (k) {	
				case 'members':	// para colas
					refreshAgents(obj.memberRefresh);
					//pbxobject.setMembers(obj[k]);
					break;
				case 'active':	// para extensiones
					pbxobject.setActive(obj[k]);
					break;
				case 'chan':	// para líneas DAHDI
					pbxobject.setChan(obj[k]);
					break;
				default:
					pbxobject.set(k, obj[k]);
					break;
				}
			} else {
				console.debug('Could not locate controller or object for:');
				console.debug(obj);
			}
		},
		
		deletePBXObject: function(obj) {
			//
		},
		
		// Mandar a actualizar al servidor el tamaño del panel
		updatePanelSize: function(sourcePanel, width, height) {
			var leftPanels = ['Extension', 'TrunksSIP', 'Trunks'];
			var rightPanels = ['Area1', 'Area2', 'Area3', 'Area4', 'Area5', 'Area6', 'Area7', 'Area8', 'Area9', 'Queues', 'Conferences', 'Parkinglots'];
			

			var panelGroups = {
				'left': [this.extensions, this.iptrunks, this.dahdi],
				'right': [this.area1, this.area2, this.area3, this.queues, this.conferences, this.parkinglots]
			};
			var pgroup = null;

			for (var k in panelGroups) {
				if (panelGroups[k].findBy('name', sourcePanel) != null)
					pgroup = k;
			}
			if (pgroup == null) return;
			
			for (var i = 0; i < panelGroups[pgroup].length; i++) {
				panelGroups[pgroup][i].set('width', width);
			}
			$.post('index.php?menu=' + module_name + '&rawmode=yes', {
				menu:			module_name, 
				rawmode:		'yes',
				action:			'updatePanelSize',
				width:			width,
				panelgroup:		pgroup
			},
			function(respuesta) {
				verificar_error_session(respuesta);
				if (respuesta.status == 'error') {
					alert(respuesta.message);
				}
			}.bind(this), 'json');
		}

	});
	
	App.Router.map(function() {
		this.resource('desktop', { path: '/' }, function () {
			/*
			this.route('getconfiglog');
			this.route('endpointconfig', { path: '/endpointconfig/:id_endpoint' });
			*/
		});
	});
	
	App.DesktopRoute = Ember.Route.extend({
		/*
		setupController: function(controller, model) {
			controller.loadModels();
		}
		*/
	});
});



function initializeJQueryUIViews()
{
	// Put jQuery UI inside its own namespace
	JQ = {};

	// Create a new mixin for jQuery UI widgets using the Ember
	// mixin syntax.
	JQ.Widget = Em.Mixin.create({
	  // When Ember creates the view's DOM element, it will call this
	  // method.
	  didInsertElement: function() {
		// Make jQuery UI options available as Ember properties
	    var options = this._gatherOptions();

	    // Make sure that jQuery UI events trigger methods on this view.
	    this._gatherEvents(options);

	    // Create a new instance of the jQuery UI widget based on its `uiType`
	    // and the current element.
	    //var ui = jQuery.ui[this.get('uiType')](options, this.get('element'));
	    var uiType = this.get('uiType');
	    var ui = this.$();
	    if (typeof uiType == 'object') {
	    	for (var i = 0; i < uiType.length; i++)
	    		ui = ui[uiType[i]](options);
	    } else {
	    	//ui = this.$()[uiType](options);
	    	ui = ui[uiType](options);
	    }

	    // Save off the instance of the jQuery UI widget as the `ui` property
	    // on this Ember view.
	    this.set('ui', ui);
	  },

	  // When Ember tears down the view's DOM element, it will call
	  // this method.
	  willDestroyElement: function() {
		var ui = this.get('ui');
	    if (ui) {
	      // Tear down any observers that were created to make jQuery UI
	      // options available as Ember properties.
	      var observers = this._observers;
	      for (var prop in observers) {
	        if (observers.hasOwnProperty(prop)) {
	          this.removeObserver(prop, observers[prop]);
	        }
	      }
	      //ui._destroy();
		  var uiType = this.get('uiType');
          if (typeof uiType == 'object') {
		  	for (var i = 0; i < uiType.length; i++)
		  		ui = ui[uiType[i]]('destroy');
		  } else {
			  ui = ui[uiType]('destroy');
		  }
	    }
	  },

	  // Each jQuery UI widget has a series of options that can be configured.
	  // For instance, to disable a button, you call
	  // `button.options('disabled', true)` in jQuery UI. To make this compatible
	  // with Ember bindings, any time the Ember property for a
	  // given jQuery UI option changes, we update the jQuery UI widget.
	  _gatherOptions: function() {
	    var uiOptions = this.get('uiOptions'), options = {};

	    // The view can specify a list of jQuery UI options that should be treated
	    // as Ember properties.
	    if (uiOptions != null) uiOptions.forEach(function(key) {
	      options[key] = this.get(key);

	      // Set up an observer on the Ember property. When it changes,
	      // call jQuery UI's `setOption` method to reflect the property onto
	      // the jQuery UI widget.
	      var observer = function() {
	        var value = this.get(key);
	        var uiType = this.get('uiType');
	        this.get('ui')[uiType]('option', key, value);
	        //this.get('ui')._setOption(key, value);
	      };

	      this.addObserver(key, observer);

	      // Insert the observer in a Hash so we can remove it later.
	      this._observers = this._observers || {};
	      this._observers[key] = observer;
	    }, this);

	    return options;
	  },

	  // Each jQuery UI widget has a number of custom events that they can
	  // trigger. For instance, the progressbar widget triggers a `complete`
	  // event when the progress bar finishes. Make these events behave like
	  // normal Ember events. For instance, a subclass of JQ.ProgressBar
	  // could implement the `complete` method to be notified when the jQuery
	  // UI widget triggered the event.
	  _gatherEvents: function(options) {
	    var uiEvents = this.get('uiEvents') || [], self = this;

	    uiEvents.forEach(function(event) {
	      var callback = self[event];

	      if (callback) {
	        // You can register a handler for a jQuery UI event by passing
	        // it in along with the creation options. Update the options hash
	        // to include any event callbacks.
	        options[event] = function(event, ui) { return callback.call(self, event, ui); };
	      }
	    });
	  }
	});
	/*
	// Create a new Ember view for the jQuery UI Button widget
	JQ.Button = Em.View.extend(JQ.Widget, {
	  uiType: 'button',
	  uiOptions: ['label', 'disabled'],

	  tagName: 'button'
	});
	
	// Create a new Ember view for the jQuery UI Buttonset widget
	JQ.ButtonSet = Em.View.extend(JQ.Widget, {
	  uiType: 'buttonset'
	  //uiOptions: ['label', 'disabled']
	});
	// Create a new Ember view for the jQuery UI Progress Bar widget
	JQ.ProgressBar = Em.View.extend(JQ.Widget, {
	  uiType: 'progressbar',
	  uiOptions: ['value', 'max'],
	  uiEvents: ['change', 'complete']
	});
	*/
}




function formatoMilisegundo(msec)
{
	var tiempo = [0, 0, 0];
	tiempo[0] = (msec - (msec % 1000)) / 1000;
	tiempo[1] = (tiempo[0] - (tiempo[0] % 60)) / 60;
	tiempo[0] %= 60;
	tiempo[2] = (tiempo[1] - (tiempo[1] % 60)) / 60;
	tiempo[1] %= 60;
	var i = 0;
	for (i = 0; i < 3; i++) { if (tiempo[i] <= 9) tiempo[i] = "0" + tiempo[i]; }
	return tiempo[2] + ':' + tiempo[1] + ':' + tiempo[0];
}


//////////////////////////////////////////// BUTTONS CONTROL ////////////////////////////////////////////////////////////////////////////

var selectedContainer = null;
var selectedExtension = null;
var selectedTech 			= null;
var line1							= null;
var line2							= null;

function selectExtension(element, titleData) {
		//console.log(element);

    var idContainer = element[0].nodeValue;
    var classAttribute = element[1];
    var dataIdAttrAttribute = element[2];

    // Verifica si el contenedor tiene la clase 'pbxactive'
    var hasPbxActiveClass = classAttribute.value.includes('pbxactive');

    // Al principio, mantén las variables locales
    var localTech 			= null;
    var localExtension 	= null;
    var channel1 				= null;
    var channel2				= null;

    // Si se hace clic en el mismo contenedor, desmarca
    if (selectedContainer && selectedContainer.id === idContainer) {
        selectedContainer.style.border = ""; // Desmarca el contenedor
        selectedContainer = null;
        selectedTech 			= null;
        selectedExtension = null;
        line1							= null;
        line2							= null;

        return;
    }

    // Obtener datos del contenedor si tiene la clase 'pbxactive'
    if (hasPbxActiveClass) {
        // Obtener el valor del atributo "data-idattr"
        if (dataIdAttrAttribute) {
            var dataIdAttrValue = dataIdAttrAttribute.value;

            // Dividir el valor en "tech" y "extension"
            var parts = dataIdAttrValue.split('/');
            if (parts.length === 2) {
                localTech = parts[0]; // Mantén las variables locales
                localExtension = parts[1].replace(/"/g, ''); // Mantén las variables locales
            }

            if (titleData.title1) {
						    const regexPattern = new RegExp(localTech + '/' + localExtension + '-([\\w-]+)');
						    
						    for (const key in titleData) {
						        if (titleData.hasOwnProperty(key)) {
						            const match = titleData[key].match(regexPattern);
						            if (match) {
						                if (!channel1) {
						                    channel1 = match[0];
						                } else if (!channel2) {
						                    channel2 = match[0];
						                    break; // Salir del bucle después de encontrar el segundo valor
						                }
						            }
						        }
						    }
						}
						// Mostrar los valores de line1 y line2 en la consola
						//console.log("line1:", channel1);
						//console.log("line2:", channel2);
        }
    } else {
    	if (selectedContainer){
    	selectedContainer.style.border = ""; // Desmarca el contenedor
    	}
        selectedContainer = null;
        selectedTech 			= null;
        selectedExtension = null;
        line1							= null;
        line2							= null;

    }

    // Desmarca el contenedor previamente seleccionado
    if (selectedContainer) {
        selectedContainer.style.border = "";
    }

    // Actualiza las variables globales con las locales
    selectedTech 			= localTech;
    selectedExtension = localExtension;
    line1							= channel1;
    line2							= channel2;

    // Marca el nuevo contenedor si tiene la clase 'pbxactive'
    if (hasPbxActiveClass) {
        var containerExtension = document.getElementById(idContainer);
        if (containerExtension) {
            containerExtension.style.border = "2px solid #003bec";
            selectedContainer = containerExtension;
        }
    }
}


document.addEventListener("click", function(event) {
  setTimeout(function() {
    if (selectedContainer && !event.target.closest(".ExtensionStyle")) {
      selectedContainer.style.border = ""; // Desmarca el contenedor cuando se hace clic en cualquier otro lugar del documento
      selectedContainer = null;
      selectedTech 			= null;
			selectedExtension = null;
			line1							= null;
			line2							= null;
    }
  }, 0);
});



function buttonAction(userExt, userTech, action){

	var sameUser 	 = parseInt(userExt);
	var userActive = false;

		if (userTech === 0){
			userTech = 'PJSIP'
		} else {
			userTech = 'SIP'
		}

		var divToFind = document.querySelector('div[data-idattr="'+userTech+'/'+userExt+'"]');

		//console.log(divToFind.attributes[1].textContent);

		if (divToFind && divToFind.attributes[1].textContent.includes('pbxactive')) {
		    userActive = true;
		}

	console.log(userActive);
	if (action === "Voicemail") {
		$.ajax({
					url: "/modules/"+module_name+"/libs/buttonActions.php",
					   type: "get", //send it through get method
					      data: {
								    	userExt: 						userExt,
								    	userTech: 					userTech, 
					            action: 						action,
					            },
						          success: function(response) {
						          console.log(response);
						          selectedExtension = null;
											selectedTech = null;
					            },
						          error: function(xhr) {
						          //Do Something to handle error
						          console.log(xhr);
						          alert("Se ha producido un error al realizar la accion.")
					            }
				});

	}else {

		if (!selectedExtension) {
				buttonWarning(1);
				selectedExtension = null;
				selectedTech = null;
		} else if (sameUser === parseInt(selectedExtension)){
				buttonWarning(2);
				selectedExtension = null;
				selectedTech = null;
		} else if (userActive !== true){
				buttonWarning(4);
				selectedExtension = null;
				selectedTech = null;
		}else {
				$.ajax({
					url: "/modules/"+module_name+"/libs/buttonActions.php",
					   type: "get", //send it through get method
					      data: {
								    	userExt: 						userExt,
								    	userTech: 					userTech, 
					            selectedExtension: 	parseInt(selectedExtension),
					            selectedTech: 			selectedTech,
					            action: 						action,
					            },
						          success: function(response) {
						          console.log(response);
						          selectedExtension = null;
											selectedTech = null;
					            },
						          error: function(xhr) {
						          //Do Something to handle error
						          console.log(xhr);
						          alert("Se ha producido un error al realizar la accion.")
					            }
				});
		}
	}

	selectedExtension = null;
	selectedTech = null;
	
};

function buttonHangup(userExt, userTech, line, action){
	var userLoged = parseInt(userExt);

	if (userTech === 0){
		userTech = 'PJSIP'
	} else {
		userTech = 'SIP'
	}

	if (!selectedExtension) {
			buttonWarning(1);
				selectedExtension = null;
				selectedTech 			= null;
				line1							= null;
				line2							= null;
	} else if (line === null){
			buttonWarning(3);
				selectedExtension = null;
				selectedTech 			= null;
				line1							= null;
				line2							= null;
	} else {
					$.ajax({
				          url: "/modules/"+module_name+"/libs/buttonActions.php",
				          type: "get", //send it through get method
				             data: {
							    					userExt: 						userExt,
							    					userTech: 					userTech, 
				                    selectedExtension: 	parseInt(selectedExtension),
				                    selectedTech: 			selectedTech,
				                    action: 						action,
				                    line: 							line,
				                    },
					                    success: function(response) {
					                    console.log(response);
					                    selectedExtension = null;
															selectedTech 			= null;
															line1							= null;
															line2							= null;
				                    },
					                    error: function(xhr) {
					                    //Do Something to handle error
					                    console.log(xhr);
					                    alert("Se ha producido un error al realizar la accion.")
				                  	}
				        });
	}

	selectedExtension = null;
	selectedTech 			= null;
	line1							= null;
	line2							= null;
};



function buttonWarning(number) {
    const notificationContainer = document.createElement('div');
    notificationContainer.classList.add('notification-container');
    if (number === 1) {
        notificationContainer.innerHTML = `
            <div style="position: relative;">
                <p class="notification-message">¡Debe seleccionar una extension de destino!</p>
            </div>
        `;
    } else if (number === 2) {
        notificationContainer.innerHTML = `
            <div style="position: relative;">
                <p class="notification-message">¡No puede seleccionar su propia extension como destino!</p>
            </div>
        `;
    } else if (number === 3) {
        notificationContainer.innerHTML = `
            <div style="position: relative;">
                <p class="notification-message">¡La extension seleccionada, no cuenta con llamadas en esta linea!</p>
            </div>
        `;
    } else if (number === 4) {
        notificationContainer.innerHTML = `
            <div style="position: relative;">
                <p class="notification-message">¡Su extension no se encuentra conectada para realizar esta acción!</p>
            </div>
        `;
    }else if (number === 5) {
        notificationContainer.innerHTML = `
            <div style="position: relative;">
                <p class="notification-message">¡Su cuenta no tiene una extension asociada!</p>
            </div>
        `;
    }

    document.body.appendChild(notificationContainer);

    setTimeout(function() {
        notificationContainer.style.opacity = '1';
    }, 100);

    setTimeout(function() {
        notificationContainer.style.opacity = '0';

        setTimeout(function() {
            document.body.removeChild(notificationContainer);
        }, 500);
    }, 2500);
}



////////////////////////////////////////////AGENTS CONTROL STATUS ////////////////////////////////////////////////////////////////////////////

let solicitudEnCurso = false;
let ultimoTiempoSolicitud = 0;

function refreshQueues() {
    if (solicitudEnCurso) {
        return; // Evitar solicitudes adicionales si una ya está en curso
    }

    const tiempoActual = Date.now();
    if (tiempoActual - ultimoTiempoSolicitud < 2000) {
        return; // Evitar solicitudes adicionales dentro de los últimos 2 segundos
    }

    solicitudEnCurso = true;
    
    // Realizar la solicitud AJAX al servidor para obtener los datos
    fetch('/modules/' + module_name + '/libs/utilities.php')
        .then(response => response.json())
        .then(data => {
            actualizarUI(data);
            solicitudEnCurso = false;
            ultimoTiempoSolicitud = Date.now();
        })
        .catch(error => {
            console.error('Error al obtener datos:', error);
            solicitudEnCurso = false;
        });
  setInterval(refreshQueues, 300000);
}

function actualizarUI(data) {
    // Recorre los datos y crea o actualiza los elementos correspondientes
    data.forEach(queueData => {
        const queueNumber = queueData.Queue.trim();
        const divConDataIDAttr = document.querySelector(`[data-idattr="QUEUE/${queueNumber}"]`);
        
        if (divConDataIDAttr) {
            const queueAgentsDiv = divConDataIDAttr.querySelector('.Queue-Agents');

            if (queueAgentsDiv) {
                var html = '';

                if (queueData.Members) {
                    html += '<span>';
                    html += '<strong>Agents:</strong>';
                    html += '</span>';
                    html += '<br>';

                    queueData.Members.forEach(member => {
                        html += generateAgentHTML(member, queueNumber);
                    });
                }

                queueAgentsDiv.innerHTML = html;
            }
            const queueParameters = divConDataIDAttr.querySelector('.queueParameters');
            if (queueParameters) {
            	var html = '';
            	html += '<div>';
							html += '<span class="answered" style="margin-right: 20px"><strong title="Estos datos se actualizan cada 5 minutos.">Answered:</strong> (' + queueData.Completed + ')</span>';
							html += '<span class="abandoned"><strong title="Estos datos se actualizan cada 5 minutos.">Abandoned:</strong> (' + queueData.Abandoned + ')</span>';
							html += '</div>';
              queueParameters.innerHTML = html;
            }
        }
    });
}


function generateAgentHTML(member) {
    var html = '';
    var status = memberStatus(member.Status, member.Paused);

    console.log(member);

    html += '<div class="agent-container" data-member-number="'+ member.Location + '">';
    html += status.circleHTML; // Aquí utilizamos el HTML del círculo
    html += '<span class="agent-info">' + status.statusImage +'</span>';
    if (member.Name.includes("Local/")) {
    	html +=	'<span style="margin-left:1px">'+ member.Name +'</span><br>';
		} else {
			html +=	'<span style="margin-left:1px">'+ member.Location +' - '+ member.Name +'</span><br>';
		}
    html += '</div>';

    return html;
}

function refreshAgents(member) {
  if (member) {
    member.forEach(item => {
      //const regex = /\/(.*?)@/;
      //const match = item.Interface.match(regex);
      const afterSlash = item.Interface.split('/')[1];
      const result = afterSlash.includes('@') ? afterSlash.split('@')[0] : afterSlash;

      if (result) {
        const memberNumber = result;
        // Buscar todos los elementos div con el atributo data-member-number igual al valor extraído
        const divsToUpdate = document.querySelectorAll('.agent-container[data-member-number="'+memberNumber+'"]');

        divsToUpdate.forEach(divToUpdate => {
          // Actualizar el contenido de cada div
          var status = memberStatus(item.Status, item.Paused);
          var html = status.circleHTML; // Aquí utilizamos el HTML del círculo
          html += '<span class="agent-info">'+status.statusImage+''+memberNumber+' - '+ item.MemberName +'</span><br>';
          divToUpdate.innerHTML = html;
        });
      }
    });
  }
}

function memberStatus(status, paused) {
    if (paused !== "0" && status !== "5") {
        return {
            statusImage: "<i class='fa fa-pause' style='padding-right:4px; padding-left:1px; font-size:13px'></i>",
            circleHTML: '<div class="circle green" title="En Pausa (DND Agent)"></i></div>'
        };
    }

    switch (status) {
        case '1':
            return {
                statusImage: '<img src="/modules/'+module_name+'/images/agent-available.png" alt="Disponible" style="padding-right:1px;"/>',
                circleHTML: '<span class="circle green" title="Disponible"></span>'
            };
        case '2':
            return {
                statusImage: '<img src="/modules/'+module_name+'/images/agent-busy.png" alt="Ocupado" style="padding-right:1px;"/>',
                circleHTML: '<div class="circle orange" title="Ocupado"></div>'
            };
        case '4':
            return {
                statusImage: '<img src="/modules/'+module_name+'/images/agent-busy.png" alt="Invalidado" style="padding-right:1px;"/>',
                circleHTML: '<div class="circle strong-red" title="Invalidado"></div>'
            };
        case '5':
            return {
                statusImage: '<img src="/modules/'+module_name+'/images/agent-disconected.png" alt="Desconectado" style="padding-right:1px;"/>',
                circleHTML: '<div class="circle strong-red" title="Desconectado"></div>'
            };
        case '6':
            return {
                statusImage: '<img src="/modules/'+module_name+'/images/agent-ringing.gif" alt="Ringeando" style="padding-right:1px;"/>',
                circleHTML: '<div class="circle yellow" title="Ringeando"></div>'
            };
        case '7':
            return {
                statusImage: '<img src="/modules/'+module_name+'/images/agent-busy.png" alt="Ring In Use" style="padding-right:1px;"/>',
                circleHTML: '<div class="circle orange-yellow" title="Ring In Use"></div>'
            };
        case '8':
            return {
                statusImage: "<i class='fa fa-stop' style='padding-right:4px; padding-left:1px; font-size:13px'></i>",
                circleHTML: '<div class="circle orange" title="Agent On Hold"></div>'
            };
        default:
            return {
                statusImage: "",
                circleHTML: '<div class="circle unknown"></div>'
            };
    }
}


function refreshQueueParameters(queue) {
    var cadena = queue;
    var expresionRegular = /\/(.+)/;
    var coincidencias = cadena.match(expresionRegular);
    var cleanQueue = coincidencias ? coincidencias[1] : null;

    // Enviar el valor a PHP como una cadena simple en lugar de JSON
    fetch('/modules/'+module_name+'/libs/utilities.php', {
        method: 'POST',
        body: 'cleanQueue=' + cleanQueue, // Enviar como una cadena simple
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded' // Usar el tipo de contenido adecuado
        }
    })
    .then(response => response.json())
    .then(data => {
        refreshQueues();
    })
    .catch(error => {
        console.error('Error:', error);
    });
}


//////////////////////////////////////////// COUNT EXTENSIONS CONTROL ////////////////////////////////////////////////////////////////////////////


var extensionCounts = {
  totalExtensions: 0
};

function contarPhones(obj) {
  extensionCounts.totalExtensions++;
}

function contarElementos() {
  const extensionLists = document.querySelectorAll('#Extensions-List');
  let conteoConActiveTotal = 0;
  
  extensionLists.forEach((extensionList) => {
    if (extensionList) {
      const elementos = extensionList.querySelectorAll('.pbxactive');
      const conteoConActive = elementos.length;
      conteoConActiveTotal += conteoConActive;
    }
  });

  const conteoSinActiveTotal = extensionCounts.totalExtensions - conteoConActiveTotal;

  // Llama a showPhonesCount con los conteos totales
  showPhonesCount(conteoConActiveTotal, conteoSinActiveTotal);
}

function showPhonesCount(conteoConActive, conteoSinActive) {
  // Obtener los elementos span por su clase
  var spanRegistered = document.querySelector('.Count-Registered');
  var spanNotRegistered = document.querySelector('.Count-NotRegistered');

	  // Verificar si se encontraron los elementos
	  if (spanRegistered && spanNotRegistered) {
	    // Actualizar el contenido de los elementos span con los valores
	    spanRegistered.textContent = conteoConActive;
	    spanNotRegistered.textContent = conteoSinActive;

	    //console.log(conteoConActive);
	    //console.log(conteoSinActive);
	  }
}
setInterval(() => {
	contarElementos();
}, 5000);
