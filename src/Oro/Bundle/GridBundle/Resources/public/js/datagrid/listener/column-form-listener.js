/* global define */
define(['jquery', 'underscore', 'oro/translator', 'oro/mediator', 'oro/modal', 'oro/grid/abstract-listener'],
function($, _, __, mediator, Modal, AbstractListener) {
    'use strict';

    /**
     * Listener for entity edit form and datagrid
     *
     * @export  oro/grid/column-form-listener
     * @class   oro.grid.ColumnFormListener
     * @extends oro.grid.AbstractListener
     */
    return AbstractListener.extend({

        /** @param {Object} */
        selectors: {
            included: null,
            excluded: null
        },

        /**
         * Initialize listener object
         *
         * @param {Object} options
         */
        initialize: function(options) {
            if (!_.has(options, 'selectors')) {
                throw new Error('Field selectors is not specified');
            }
            this.selectors = options.selectors;

            AbstractListener.prototype.initialize.apply(this, arguments);
        },

        /**
         * Set datagrid instance
         *
         * @param {oro.grid.Grid} datagrid
         */
        setDatagridAndSubscribe: function(datagrid) {
            AbstractListener.prototype.setDatagridAndSubscribe.apply(this, arguments);

            this.listenTo(this.datagrid.getRefreshAction(), 'preExecute', this._onExecuteRefreshAction);
            this.listenTo(this.datagrid.getResetAction(), 'preExecute', this._onExecuteResetAction);

            this._clearState();
            this._restoreState();

            /**
             * Restore include/exclude state from pagestate
             */
            mediator.bind(
                "pagestate_restored",
                function() {
                    this._restoreState();
                },
                this
            );
        },

        /**
         * Fills inputs referenced by selectors with ids need to be included and to excluded
         *
         * @param {*} id model id
         * @param {Backbone.Model} model
         * @protected
         */
        _processValue: function(id, model) {
            var original = this.get('original');
            var included = this.get('included');
            var excluded = this.get('excluded');

            var isActive = model.get(this.columnName);
            var originallyActive;
            if (_.has(original, id)) {
                originallyActive = original[id];
            } else {
                originallyActive = !isActive;
                original[id] = originallyActive;
            }

            if (isActive) {
                if (originallyActive) {
                    included = _.without(included, [id]);
                } else {
                    included = _.union(included, [id]);
                }
                excluded = _.without(excluded, id);
            } else {
                included = _.without(included, id);
                if (!originallyActive) {
                    excluded = _.without(excluded, [id]);
                } else {
                    excluded = _.union(excluded, [id]);
                }
            }

            this.set('included', included);
            this.set('excluded', excluded);
            this.set('original', original);

            this._synchronizeState();
        },

        /**
         * Clears state of include and exclude properties to empty values
         *
         * @private
         */
        _clearState: function () {
            this.set('included', []);
            this.set('excluded', []);
            this.set('original', {});
        },

        /**
         * Synchronize values of include and exclude properties with form fields and datagrid parameters
         *
         * @private
         */
        _synchronizeState: function () {
            var included = this.get('included');
            var excluded = this.get('excluded');
            if (this.selectors.included) {
                $(this.selectors.included).val(included.join(','));
            }
            if (this.selectors.excluded) {
                $(this.selectors.excluded).val(excluded.join(','));
            }
            this.datagrid.setAdditionalParameter('data_in', included);
            this.datagrid.setAdditionalParameter('data_not_in', excluded);
        },

        /**
         * Explode string into int array
         *
         * @param string
         * @return {Array}
         * @private
         */
        _explode: function(string) {
            if (!string) {
                return [];
            }
            return _.map(string.split(','), function(val) {return val ? parseInt(val, 10) : null});
        },

        /**
          * Restore values of include and exclude properties
          *
          * @private
          */
        _restoreState: function () {
            var included = '';
            var excluded = '';
            if (this.selectors.included && $(this.selectors.included).length) {
                included = this._explode($(this.selectors.included).val());
                this.set('included', included);
            }
            if (this.selectors.excluded && $(this.selectors.excluded).length) {
                excluded = this._explode($(this.selectors.excluded).val());
                this.set('excluded', excluded)
            }
            if (included || excluded) {
                this.datagrid.setAdditionalParameter('data_in', included);
                this.datagrid.setAdditionalParameter('data_not_in', excluded);
                var columnName = this.columnName;
                var dataField = this.dataField;
                this.datagrid.collection.each(function(model) {
                    if (_.indexOf(included, model.get(dataField)) !== -1) {
                        model.set(columnName, true);
                    }
                    if (_.indexOf(excluded, model.get(dataField)) !== -1) {
                        model.set(columnName, false);
                    }
                });
            }
         },

        /**
         * Confirms refresh action that before it will be executed
         *
         * @param {oro.grid.AbstractAction} action
         * @param {Object} options
         * @private
         */
        _onExecuteRefreshAction: function(action, options) {
            this._confirmAction(action, options, 'refresh', {
                title: __('Refresh Confirmation'),
                content: __('Your local changes will be lost. Are you sure you want to refresh grid?')
            });
        },

        /**
         * Confirms reset action that before it will be executed
         *
         * @param {oro.grid.AbstractAction} action
         * @param {Object} options
         * @private
         */
        _onExecuteResetAction: function(action, options) {
            this._confirmAction(action, options, 'reset', {
                title: __('Reset Confirmation'),
                content: __('Your local changes will be lost. Are you sure you want to reset grid?')
            });
        },

        /**
         * Asks user a confirmation if there are local changes, if user confirms then clears state and runs action
         *
         * @param {oro.grid.AbstractAction} action
         * @param {Object} actionOptions
         * @param {String} type "reset" or "refresh"
         * @param {Object} confirmModalOptions Options for confirm dialog
         * @private
         */
        _confirmAction: function(action, actionOptions, type, confirmModalOptions) {
            this.confirmed = this.confirmed || {};
            if (!this.confirmed[type] && this._hasChanges()) {
                actionOptions.doExecute = false; // do not execute action until it's confirmed
                this._openConfirmDialog(type, confirmModalOptions, function () {
                    // If confirmed, clear state and run action
                    this.confirmed[type] = true;
                    this._clearState();
                    this._synchronizeState();
                    action.run();
                });
            }
            this.confirmed[type] = false;
        },

        /**
         * Returns TRUE if listener contains user changes
         *
         * @return {Boolean}
         * @private
         */
        _hasChanges: function() {
            return !_.isEmpty(this.get('included')) || !_.isEmpty(this.get('excluded'));
        },

        /**
         * Opens confirm modal dialog
         */
        _openConfirmDialog: function(type, options, callback) {
            this.confirmModal = this.confirmModal || {};
            if (!this.confirmModal[type]) {
                this.confirmModal[type] = new Modal(_.extend({
                    title: __('Confirmation'),
                    okText: __('Ok, got it.'),
                    className: 'modal modal-primary',
                    okButtonClass: 'btn-primary btn-large'
                }, options));
                this.confirmModal[type].on('ok', _.bind(callback, this));
            }
            this.confirmModal[type].open();
        }
    });
});
