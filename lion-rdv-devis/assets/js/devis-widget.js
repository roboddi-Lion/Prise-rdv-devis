( function () {
	'use strict';

	var root = document.getElementById( 'lion-rdv-devis-widget' );
	if ( ! root || typeof lionRdvDevisSettings === 'undefined' ) {
		return;
	}

	var i18n = lionRdvDevisSettings.i18n;
	var restUrl = lionRdvDevisSettings.restUrl.replace( /\/$/, '' );

	var state = {
		step: 'service',
		service: null,
		serviceQuestions: [],
		days: [],
		activeDayIndex: 0,
		selectedSlot: null,
		formLoadedAt: Date.now(),
		submitting: false,
	};

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( 'text' === key ) {
				node.textContent = attrs[ key ];
			} else if ( 0 === key.indexOf( 'on' ) && 'function' === typeof attrs[ key ] ) {
				node.addEventListener( key.slice( 2 ), attrs[ key ] );
			} else {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function clear( node ) {
		while ( node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function showMessage( container, text, type ) {
		var msg = el( 'div', { class: 'lion-rdv-devis-message is-' + type, text: text } );
		container.insertBefore( msg, container.firstChild );
	}

	var PROGRESS_STEPS = [
		{ key: 'service', label: function () { return i18n.stepProject; } },
		{ key: 'slots', label: function () { return i18n.stepSlot; } },
		{ key: 'form', label: function () { return i18n.stepContact; } },
	];

	function renderProgress( activeKey ) {
		var activeIndex = PROGRESS_STEPS.map( function ( s ) { return s.key; } ).indexOf( activeKey );
		var wrap = el( 'div', { class: 'lion-rdv-devis-progress' } );

		PROGRESS_STEPS.forEach( function ( step, index ) {
			var state = index < activeIndex ? 'is-done' : ( index === activeIndex ? 'is-active' : '' );
			wrap.appendChild(
				el( 'div', { class: 'lion-rdv-devis-progress-step' + ( state ? ' ' + state : '' ) }, [
					el( 'span', { class: 'lion-rdv-devis-progress-dot', text: index < activeIndex ? '✓' : String( index + 1 ) } ),
					el( 'span', { class: 'lion-rdv-devis-progress-label', text: step.label() } ),
				] )
			);
		} );

		return wrap;
	}

	// Reprend la petite barre à 4 couleurs (ambre/terracotta/bleu/marine) que
	// lion-renovation.fr affiche devant ses titres de section — une touche
	// de marque directement empruntée au site plutôt qu'un motif générique.
	function renderBrandBar() {
		return el( 'div', { class: 'lion-rdv-devis-brand-bar' }, [
			el( 'span' ),
			el( 'span' ),
			el( 'span' ),
			el( 'span' ),
		] );
	}

	// Toutes les étapes (sauf l'écran final) partagent la même structure de
	// base : la barre de marque, puis l'indicateur de progression.
	function stepShell( activeKey ) {
		var stepEl = el( 'div', { class: 'lion-rdv-devis-step' } );
		stepEl.appendChild( renderBrandBar() );
		stepEl.appendChild( renderProgress( activeKey ) );
		return stepEl;
	}

	function render() {
		clear( root );
		if ( 'service' === state.step ) {
			renderServiceStep();
		} else if ( 'slots' === state.step ) {
			renderSlotsStep();
		} else if ( 'form' === state.step ) {
			renderFormStep();
		} else if ( 'done' === state.step ) {
			renderDoneStep();
		}
	}

	function renderServiceStep() {
		var stepEl = stepShell( 'service' );
		stepEl.appendChild( el( 'h2', { text: i18n.chooseService } ) );

		var grid = el( 'div', { class: 'lion-rdv-devis-service-grid' } );

		( lionRdvDevisSettings.services || [] ).forEach( function ( service ) {
			grid.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: 'lion-rdv-devis-service-card',
						onclick: function () {
							selectService( service.key );
						},
					},
					[
						el( 'strong', { text: service.label } ),
						el( 'span', { text: service.description } ),
					]
				)
			);
		} );

		stepEl.appendChild( grid );
		root.appendChild( stepEl );
	}

	function selectService( serviceKey ) {
		var found = ( lionRdvDevisSettings.services || [] ).filter( function ( s ) {
			return s.key === serviceKey;
		} )[ 0 ];

		state.service = serviceKey;
		state.serviceQuestions = found ? ( found.questions || [] ) : [];
		state.step = 'slots';
		state.days = [];
		render();
		fetchSlots();
	}

	function fetchSlots() {
		var stepEl = stepShell( 'slots' );
		stepEl.appendChild( backButton( 'service' ) );
		stepEl.appendChild(
			el( 'div', { class: 'lion-rdv-devis-loading' }, [
				el( 'span', { class: 'lion-rdv-devis-spinner' } ),
				document.createTextNode( i18n.loadingSlots ),
			] )
		);
		clear( root );
		root.appendChild( stepEl );

		fetch( restUrl + '/creneaux?service=' + encodeURIComponent( state.service ) )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				if ( ! result.ok || ! result.data.success ) {
					state.step = 'slots';
					state.days = [];
					render();
					showMessage( root.querySelector( '.lion-rdv-devis-step' ), ( result.data && result.data.message ) || i18n.genericError, 'error' );
					return;
				}
				state.days = result.data.days || [];
				state.activeDayIndex = 0;
				render();
			} )
			.catch( function () {
				state.step = 'slots';
				state.days = [];
				render();
				showMessage( root.querySelector( '.lion-rdv-devis-step' ), i18n.genericError, 'error' );
			} );
	}

	function backButton( targetStep ) {
		return el(
			'button',
			{
				type: 'button',
				class: 'lion-rdv-devis-back',
				onclick: function () {
					state.step = targetStep;
					render();
				},
			},
			[ document.createTextNode( i18n.back ) ]
		);
	}

	function renderSlotsStep() {
		var stepEl = stepShell( 'slots' );
		stepEl.appendChild( backButton( 'service' ) );

		if ( ! state.days.length ) {
			stepEl.appendChild( el( 'p', { text: i18n.noSlots } ) );
			root.appendChild( stepEl );
			return;
		}

		stepEl.appendChild( el( 'h3', { text: i18n.chooseDay } ) );

		var daysWrap = el( 'div', { class: 'lion-rdv-devis-days' } );
		state.days.forEach( function ( day, index ) {
			daysWrap.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: 'lion-rdv-devis-day-btn' + ( index === state.activeDayIndex ? ' is-active' : '' ),
						text: day.label,
						onclick: function () {
							state.activeDayIndex = index;
							render();
						},
					}
				)
			);
		} );
		stepEl.appendChild( daysWrap );

		stepEl.appendChild( el( 'h3', { text: i18n.chooseTime } ) );

		var slotsWrap = el( 'div', { class: 'lion-rdv-devis-slots' } );
		var activeDay = state.days[ state.activeDayIndex ];
		activeDay.slots.forEach( function ( slot ) {
			slotsWrap.appendChild(
				el(
					'button',
					{
						type: 'button',
						class: 'lion-rdv-devis-slot-btn',
						text: slot.label,
						onclick: function () {
							state.selectedSlot = slot;
							state.selectedDayLabel = activeDay.label;
							state.formLoadedAt = Date.now();
							state.step = 'form';
							render();
						},
					}
				)
			);
		} );
		stepEl.appendChild( slotsWrap );

		root.appendChild( stepEl );
	}

	function field( key, label, type, extra ) {
		var wrap = el( 'div', { class: 'lion-rdv-devis-field' + ( extra && extra.full ? ' lion-rdv-devis-full' : '' ) } );
		wrap.appendChild( el( 'label', { for: 'lion-rdv-devis-' + key, text: label } ) );
		var inputAttrs = { id: 'lion-rdv-devis-' + key, name: key, type: type || 'text' };
		if ( extra && extra.required ) {
			inputAttrs.required = 'required';
		}
		var input = 'textarea' === type ? el( 'textarea', inputAttrs ) : el( 'input', inputAttrs );
		wrap.appendChild( input );
		return { wrap: wrap, input: input };
	}

	// Rend une question "entonnoir" (voir Lion_RDV_Devis_Settings::default_services())
	// selon son type, et retourne un accesseur getValue() uniforme quel que
	// soit l'élément de formulaire réellement utilisé (select, radios en
	// pilules, ou champ texte classique).
	function renderQuestionField( q ) {
		var labelText = q.label + ( q.required ? ' *' : '' );
		var fieldId = 'lion-rdv-devis-q-' + q.key;
		var fieldClass = 'lion-rdv-devis-field' + ( q.full ? ' lion-rdv-devis-full' : '' );

		if ( 'select' === q.type ) {
			var wrap = el( 'div', { class: fieldClass } );
			wrap.appendChild( el( 'label', { for: fieldId, text: labelText } ) );
			var selectAttrs = { id: fieldId, name: q.key };
			if ( q.required ) {
				selectAttrs.required = 'required';
			}
			var select = el( 'select', selectAttrs );
			( q.options || [] ).forEach( function ( opt ) {
				select.appendChild( el( 'option', { value: opt.value, text: opt.label } ) );
			} );
			wrap.appendChild( select );
			return { wrap: wrap, getValue: function () { return select.value; } };
		}

		if ( 'radio' === q.type ) {
			// <fieldset>/<legend> plutôt qu'un <span> : plusieurs boutons radio
			// partagent une seule question, un <label> classique ne peut
			// s'associer qu'à UN SEUL champ. lion-rdv-devis-field y est quand
			// même appliqué pour hériter du même comportement de grille — d'où
			// le border/margin/padding/min-width réinitialisés en CSS (un
			// <fieldset> a, comme un item de grille, un min-width par défaut
			// non nul qui le ferait déborder de sa colonne sinon).
			var fieldset = el( 'fieldset', { class: fieldClass } );
			fieldset.appendChild( el( 'legend', { class: 'lion-rdv-devis-pill-legend', text: labelText } ) );
			var group = el( 'div', { class: 'lion-rdv-devis-pill-group' } );
			var radios = [];
			( q.options || [] ).forEach( function ( opt, idx ) {
				var radioId = fieldId + '-' + idx;
				var radioAttrs = { type: 'radio', name: q.key, id: radioId, value: opt.value, class: 'lion-rdv-devis-pill-input' };
				if ( q.required ) {
					radioAttrs.required = 'required';
				}
				var radio = el( 'input', radioAttrs );
				radios.push( radio );
				group.appendChild( radio );
				group.appendChild( el( 'label', { for: radioId, class: 'lion-rdv-devis-pill-label', text: opt.label } ) );
			} );
			fieldset.appendChild( group );
			return {
				wrap: fieldset,
				getValue: function () {
					var checked = radios.filter( function ( r ) { return r.checked; } )[ 0 ];
					return checked ? checked.value : '';
				},
			};
		}

		// text / number / textarea
		var textWrap = el( 'div', { class: fieldClass } );
		textWrap.appendChild( el( 'label', { for: fieldId, text: labelText } ) );
		var inputAttrs = { id: fieldId, name: q.key, type: 'number' === q.type ? 'number' : 'text' };
		if ( q.required ) {
			inputAttrs.required = 'required';
		}
		var input = 'textarea' === q.type ? el( 'textarea', inputAttrs ) : el( 'input', inputAttrs );
		textWrap.appendChild( input );
		return { wrap: textWrap, getValue: function () { return input.value.trim(); } };
	}

	function renderFormStep() {
		var stepEl = stepShell( 'form' );
		stepEl.appendChild( backButton( 'slots' ) );

		stepEl.appendChild(
			el( 'div', { class: 'lion-rdv-devis-summary' }, [
				el( 'strong', { text: i18n.selectedSlot + ' : ' } ),
				document.createTextNode( state.selectedDayLabel + ' — ' + state.selectedSlot.label ),
			] )
		);

		var form = el( 'form', { novalidate: 'novalidate' } );

		form.appendChild( el( 'h3', { text: i18n.yourInfo } ) );

		var grid = el( 'div', { class: 'lion-rdv-devis-form-grid' } );

		var fFirst = field( 'first_name', i18n.firstName, 'text', { required: true } );
		var fLast = field( 'last_name', i18n.lastName, 'text', { required: true } );
		var fPhone = field( 'phone', i18n.phone, 'tel', { required: true } );
		var fEmail = field( 'email', i18n.email, 'email', { required: true } );
		var fAddress = field( 'address', i18n.address, 'text', { required: true, full: true } );
		var fPostal = field( 'postal_code', i18n.postalCode, 'text', { required: true } );
		var fCity = field( 'city', i18n.city, 'text', { required: true } );
		var fMessage = field( 'message', i18n.message, 'textarea', { full: true } );

		[ fFirst, fLast, fPhone, fEmail, fAddress, fPostal, fCity, fMessage ].forEach( function ( f ) {
			grid.appendChild( f.wrap );
		} );

		form.appendChild( grid );

		// Questions "entonnoir" propres au type de projet choisi (budget,
		// délai, puis les questions techniques spécifiques) : dégrossissent
		// le dossier du client avant la visite. Jamais bloquantes par défaut.
		var questionGetters = [];
		if ( state.serviceQuestions && state.serviceQuestions.length ) {
			form.appendChild( el( 'h3', { text: i18n.yourProject } ) );
			var questionsGrid = el( 'div', { class: 'lion-rdv-devis-form-grid' } );
			state.serviceQuestions.forEach( function ( q ) {
				var rendered = renderQuestionField( q );
				questionGetters.push( { key: q.key, getValue: rendered.getValue } );
				questionsGrid.appendChild( rendered.wrap );
			} );
			form.appendChild( questionsGrid );
		}

		var honeypot = el( 'div', { class: 'lion-rdv-devis-honeypot' } );
		var honeypotInput = el( 'input', { type: 'text', name: 'site_web', tabindex: '-1', autocomplete: 'off' } );
		honeypot.appendChild( honeypotInput );
		form.appendChild( honeypot );

		var submitBtn = el( 'button', { type: 'submit', class: 'lion-rdv-devis-submit', text: i18n.confirm } );
		form.appendChild( submitBtn );

		form.addEventListener( 'submit', function ( evt ) {
			evt.preventDefault();
			if ( state.submitting ) {
				return;
			}

			var extraAnswers = {};
			questionGetters.forEach( function ( q ) {
				var value = q.getValue();
				if ( value ) {
					extraAnswers[ q.key ] = value;
				}
			} );

			var payload = {
				service: state.service,
				start: state.selectedSlot.start,
				end: state.selectedSlot.end,
				first_name: fFirst.input.value.trim(),
				last_name: fLast.input.value.trim(),
				phone: fPhone.input.value.trim(),
				email: fEmail.input.value.trim(),
				address: fAddress.input.value.trim(),
				postal_code: fPostal.input.value.trim(),
				city: fCity.input.value.trim(),
				message: fMessage.input.value.trim(),
				extra_answers: extraAnswers,
				site_web: honeypotInput.value,
			};

			state.submitting = true;
			submitBtn.disabled = true;
			submitBtn.textContent = i18n.sending;

			fetch( restUrl + '/reserver', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( payload ),
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, status: response.status, data: data };
					} );
				} )
				.then( function ( result ) {
					state.submitting = false;
					if ( result.ok && result.data.success ) {
						state.step = 'done';
						state.doneMessage = result.data.message;
						render();
						return;
					}

					if ( 409 === result.status ) {
						state.step = 'slots';
						render();
						fetchSlots();
						return;
					}

					submitBtn.disabled = false;
					submitBtn.textContent = i18n.confirm;
					showMessage( stepEl, ( result.data && result.data.message ) || i18n.genericError, 'error' );
				} )
				.catch( function () {
					state.submitting = false;
					submitBtn.disabled = false;
					submitBtn.textContent = i18n.confirm;
					showMessage( stepEl, i18n.genericError, 'error' );
				} );
		} );

		stepEl.appendChild( form );
		root.appendChild( stepEl );
	}

	function renderDoneStep() {
		var stepEl = el( 'div', { class: 'lion-rdv-devis-step lion-rdv-devis-confirmation' } );
		stepEl.appendChild( el( 'div', { class: 'lion-rdv-devis-icon', text: '✓' } ) );
		stepEl.appendChild( el( 'p', { text: state.doneMessage } ) );
		stepEl.appendChild(
			el(
				'button',
				{
					type: 'button',
					class: 'lion-rdv-devis-submit',
					text: i18n.startOver,
					onclick: function () {
						state = {
							step: 'service',
							service: null,
							serviceQuestions: [],
							days: [],
							activeDayIndex: 0,
							selectedSlot: null,
							formLoadedAt: Date.now(),
							submitting: false,
						};
						render();
					},
				}
			)
		);
		root.appendChild( stepEl );
	}

	render();
} )();
