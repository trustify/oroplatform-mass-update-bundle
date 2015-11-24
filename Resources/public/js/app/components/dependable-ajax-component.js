define(
    ['oroui/js/app/components/base/component', 'jquery', 'underscore', 'oroui/js/mediator', 'routing'],
    function (BaseComponent, $, _, mediator, routing) {
    'use strict';

    var DependableAjaxComponent;

    DependableAjaxComponent = BaseComponent.extend({

        /** @var jQuery */
        mainElement: null,

        /** @var jQuery */
        actionBtn: null,

        /**
         * @param {Object} options
         */
        initialize: function (options) {
            // _sourceElement refers to the HTMLElement
            // that contains the component declaration
            this.$elem = options._sourceElement;
            delete options._sourceElement;

            this.mainElement = $(options.mainElementId);
            this.mainElement.on('change', _.bind(this.loadFieldToUpdate, this));

            this.actionBtn = this.$elem.parents('.ui-dialog').find('button.btn-primary');
            this.actionBtn.hide();

            DependableAjaxComponent.__super__.initialize.call(this, options);
        },

        /**
         * @param event
         */
        loadFieldToUpdate: function (event) {
            var selectedItem = $(event.currentTarget).val();

            mediator.execute('showLoading');
            var url = routing.generate('trustify_mass_update', {
                'selectedFormField': selectedItem,
                '_widgetContainer': 'dialog'
            });

            var self = this;
            $.get(url)
                .done(function(data, code, response) {
                    if (code == 'success') {
                        self.$elem.empty();
                        self.$elem.append(data)

                        self.actionBtn.show();
                    }
                }).always(function() {
                    mediator.execute('hideLoading');
                });
        }
    });

    return DependableAjaxComponent;
});
