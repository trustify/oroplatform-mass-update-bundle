define([
    'jquery',
    'orotranslation/js/translator',
    'oro/datagrid/action/mass-action',
    'oroui/js/messenger',
    'routing',
    'underscore',
    'oroui/js/mediator'
], function ($, __, MassAction, messenger, routing, _, mediator) {
    'use strict';

    var UpdateMassAction;

    /**
     * @export  oro/datagrid/action/update-mass-action
     * @class   oro.datagrid.action.UpdateMassAction
     * @extends oro.datagrid.action.MassAction
     */
    UpdateMassAction = MassAction.extend({
        widgetDefaultOptions: {
            type: 'dialog',
            multiple: false,
            'reload-grid-name': '',
            options: {
                alias: 'mass-update-widget',
                dialogOptions: {
                    title: null,
                    allowMaximize: false,
                    allowMinimize: false,
                    modal: true,
                    resizable: false,
                    maximizedHeightDecreaseBy: 'minimize-bar',
                    width: 350
                }
            }
        },

        dialogInputContainer: '.mass-update-input-container',
        fieldName: 'mass_edit_field',

        stepOptions: {},

        dialogWindowOptions: {
            'route': null,
            'route_parameters': {},
        },

        dialogWidget: null,

        /**
         * Initialize view
         *
         * @param {Object} options
         * @param {Object} [options.launcherOptions] Options for new instance of launcher object
         * @constructor
         */
        initialize: function (options) {
            UpdateMassAction.__super__.initialize.apply(this, arguments);

            this.on('preExecute', this.onPreExecute, this);
            this.on('postExecute', this.onPostExecute, this);

            // create two step action configuration
            this.stepOptions = {
                // display dialog step
                first: _.extend(
                    this.widgetDefaultOptions.options,
                    this.dialogWindowOptions,
                    {
                        url: routing.generate(
                            this.dialogWindowOptions.route,
                            this.dialogWindowOptions.route_parameters
                        ),
                        dialogOptions: {label: this.label || __('Update item')},
                        submitHandler: this.getSubmitHandler()
                    }
                ),
                // perform actual action step
                second: _.extend(
                    this.frontend_options,
                    {
                        route: this.route,
                        route_parameters: this.route_parameters
                    }
                )
            };

            this.switchToFirstStep();

            var dialogAlias = this.stepOptions.first.alias;
            var self = this;

            mediator.on(
                'widget_remove:' + dialogAlias,
                function () {
                    self.switchToFirstStep();
                    self.dialogWidget = null;
                }
            );

            mediator.on(
                'widget_dialog:open',
                function (dialogWidget) {
                    if (dialogWidget.getAlias() == dialogAlias) {
                        self.switchToSecondStep();

                        self.dialogWidget = dialogWidget;
                        dialogWidget.getWidget().find('select').on('change', _.bind(self.loadUpdateFieldForm, self));
                    }
                }
            );
        },

        /**
         * Return submit handler to perform action
         *
         * @returns {Function}
         */
        getSubmitHandler: function () {
            var massAction = this;

            return function () {
                // this - widget
                var actionParams = this.form.serializeArray(),
                    params = {};


                for (var i = 0; i < actionParams.length; i++) {
                    params[actionParams[i].name] = actionParams[i].value;
                }
                massAction.route_parameters = _.extend(massAction.route_parameters, params);
                massAction.run({});

                this.remove();
            };
        },

        /**
         * Triggers when field select changed
         */
        loadUpdateFieldForm: function (event) {
            var actionBtn = this.dialogWidget.getWidget().parents('.ui-dialog').find('button.btn-primary');

            var selectedItem = $(event.currentTarget).val();

            mediator.execute('showLoading');
            var url = routing.generate('trustify_mass_update', {
                'selectedFormField': selectedItem,
                'entityName': this.stepOptions.first.route_parameters.entityName,
                '_widgetContainer': 'dialog'
            });

            var self = this;
            var pageComponent = selectedItem;
            $.get(url)
                .done(function (data, code, response) {
                    if (code == 'success') {
                        var inputContainer = self.dialogWidget.$el.find(self.dialogInputContainer);
                        inputContainer.empty();
                        inputContainer.append(data);

                        actionBtn.show();

                        self.dialogWidget.removePageComponent(pageComponent);
                        self.dialogWidget.initPageComponents();
                        $(self.dialogInputContainer)
                            .find('select:not(.no-uniform,.select2)').uniform({selectAutoWidth: false});
                        mediator.trigger('layout:adjustHeight');
                    }
                }).always(function () {
                mediator.execute('hideLoading');
            });
        },

        switchToFirstStep: function () {
            this.frontend_handle = 'dialog';
            this.frontend_options = this.stepOptions.first;

            this.route = this.frontend_options.route;
            this.route_parameters = this.frontend_options.route_parameters;
        },

        switchToSecondStep: function () {
            // make this option writable again, against chaplin
            if (this.hasOwnProperty('frontend_handle')) {
                Object.defineProperty(this, 'frontend_handle', {writable: true});
            }

            this.frontend_handle = 'ajax';
            this.frontend_options = this.stepOptions.second;

            this.route = this.frontend_options.route;
            this.route_parameters = this.frontend_options.route_parameters;
        },

        /**
         * @param {object} event Backbone event object
         * @param {object} options Additional param options needed to stop action
         */
        onPreExecute: function (event, options) {
            this.validateMaxSelected(options);
        },

        /**
         * @param {object} options
         */
        validateMaxSelected: function (options) {
            var totalRecords;
            var validationMessage;

            var maxLength = this.max_element_count;
            var selectionState = this.datagrid.getSelectionState();
            var isInset = selectionState.inset;
            var length = selectionState.selectedIds.length;

            if (!isInset) {
                totalRecords = this.datagrid.collection.state.totalRecords;
                length = totalRecords - length;
            }

            if (length > maxLength) {
                options.doExecute = false;
                validationMessage = __('oro.entity_merge.mass_action.validation.maximum_records_error',
                    {number: maxLength});
                messenger.notificationFlashMessage('error', validationMessage);
            }

            if (length < 2) {
                options.doExecute = false;

                validationMessage = __(
                    'oro.entity_merge.mass_action.validation.minimum_records_error',
                    {number: maxLength}
                );

                messenger.notificationFlashMessage('error', validationMessage);
            }
        }
    });

    return UpdateMassAction;
});
