/**
 * Per-category menuindex: admin grid hooks (IIFE, no global pollution).
 */
(function () {
    function attachRowSelectForDrag(grid) {
        if (!grid || grid._ms2CategorySortRowSelect) {
            return;
        }
        grid._ms2CategorySortRowSelect = true;
        grid.getView().on('mousedown', function (view, rowIndex, e) {
            if (Ext.fly(e.getTarget()).findParent('a', 10)) {
                return;
            }
            var rec = grid.getStore().getAt(rowIndex);
            var sm = grid.getSelectionModel();
            if (rec && sm && !sm.isSelected(rec)) {
                sm.selectRecords([rec]);
            }
        });
    }

    function patchNotifyDrop(grid, config) {
        if (grid._ms2CategorySortDropPatched) {
            return;
        }
        grid._ms2CategorySortDropPatched = true;

        var originalInitDD = grid._initDD;
        if (typeof originalInitDD !== 'function') {
            return;
        }

        grid._initDD = function (cfg) {
            var self = this;
            var el = self.getEl();

            new Ext.dd.DropTarget(el, {
                ddGroup: self.ddGroup,
                dropMove: 'x-tree-drop-ok-append',
                dropSort: 'x-tree-drop-ok-between',
                notifyDrop: function (dd, e, data) {
                    var store = self.getStore();
                    var dragData = dd.getDragData(e);
                    var target = dragData ? store.getAt(dragData.rowIndex) : null;
                    var selections = data.selections && data.selections.length
                        ? data.selections
                        : (dragData && dragData.selections ? dragData.selections : []);

                    if (!selections.length && dragData && dragData.rowIndex !== undefined) {
                        var sourceRec = store.getAt(dragData.rowIndex);
                        if (sourceRec) {
                            selections = [sourceRec];
                        }
                    }

                    if (selections.length < 1 || !target || selections[0].id == target.id) {
                        return false;
                    }

                    var sources = [];
                    var i;
                    for (i in selections) {
                        if (!selections.hasOwnProperty(i)) {
                            continue;
                        }
                        sources.push(selections[i].id);
                    }

                    el.mask(_('loading'), 'x-mask-loading');
                    MODx.Ajax.request({
                        url: cfg.url,
                        params: {
                            action: cfg.ddAction,
                            sources: Ext.util.JSON.encode(sources),
                            target: target.id,
                        },
                        listeners: {
                            success: {
                                fn: function () {
                                    el.unmask();
                                    self.refresh();
                                    if (typeof self.reloadTree === 'function') {
                                        self.reloadTree(sources.concat([target.id]));
                                    }
                                },
                                scope: self,
                            },
                            failure: {
                                fn: function (response) {
                                    el.unmask();
                                    if (response && response.message) {
                                        MODx.msg.alert(_('error'), response.message);
                                    }
                                },
                                scope: self,
                            },
                        },
                    });

                    return true;
                },
            });
        };
    }

    function applyProductsWrap() {
        if (typeof miniShop2 === 'undefined' || !miniShop2.grid || !miniShop2.grid.Products) {
            return false;
        }
        if (miniShop2.grid.Products._ms2CategorySortWrapped) {
            return true;
        }

        var categoryId = MODx.request.id ? parseInt(MODx.request.id, 10) : 0;
        if (!categoryId) {
            return false;
        }

        var connectorUrl = (MODx.config.assets_url || MODX_ASSETS_URL || '')
            + 'components/ms2categorysort/connector.php';
        var sortAction = 'mgr/categorysort/product/sort';
        var OriginalProducts = miniShop2.grid.Products;

        var WrappedProducts = function (config) {
            config = config || {};
            config.url = connectorUrl;
            config.baseParams = config.baseParams || {};
            Ext.apply(config.baseParams, {
                action: 'mgr/categorysort/product/getlist',
                category_id: categoryId,
            });
            config.ddAction = sortAction;

            var listeners = Ext.apply({}, config.listeners || {});
            var prevRender = listeners.render;
            listeners.render = function (grid) {
                if (typeof prevRender === 'function') {
                    prevRender.call(this, grid);
                }
                attachRowSelectForDrag(grid);
                patchNotifyDrop(grid, config);
            };
            config.listeners = listeners;

            OriginalProducts.call(this, config);
        };

        Ext.apply(WrappedProducts, OriginalProducts);
        WrappedProducts.prototype = OriginalProducts.prototype;
        WrappedProducts.superclass = OriginalProducts.superclass;
        WrappedProducts._ms2CategorySortWrapped = true;

        miniShop2.grid.Products = WrappedProducts;
        Ext.reg('minishop2-grid-products', miniShop2.grid.Products);

        if (!MODx.Ajax._ms2CategorySortPatched) {
            var originalRequest = MODx.Ajax.request;
            MODx.Ajax.request = function (options) {
                options = options || {};
                options.params = options.params || {};
                if (options.params.action === sortAction) {
                    options.params.category_id = categoryId;
                    options.params.parent = categoryId;
                }
                return originalRequest.call(this, options);
            };
            MODx.Ajax._ms2CategorySortPatched = true;
        }

        return true;
    }

    if (!applyProductsWrap() && typeof Ext !== 'undefined') {
        Ext.onReady(function () {
            applyProductsWrap();
        });
    }
})();
