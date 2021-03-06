/**
 * Classe que define a lista de "Did"
 *
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v3
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.org <info@magnussolution.com>
 * 24/09/2012
 */
Ext.define('MBilling.view.didUse.List', {
    extend: 'Ext.ux.grid.Panel',
    alias: 'widget.diduselist',
    store: 'DidUse',
    initComponent: function() {
        var me = this;
        me.buttonCsv = false;
        me.allowPrint = false;
        me.buttonUpdateLot = false;
        me.allowCreate = false;
        me.allowUpdate = false;
        me.allowDelete = false;
        me.columns = [{
            header: t('Id'),
            dataIndex: 'id',
            flex: 1,
            hidden: true,
            hideable: App.user.isAdmin
        }, {
            header: t('did'),
            dataIndex: 'idDiddid',
            flex: 5
        }, {
            header: t('user'),
            dataIndex: 'idUserusername',
            filter: {
                type: 'string',
                field: 'idUser.username'
            },
            flex: 4,
            hidden: App.user.isClient,
            hideable: !App.user.isClient
        }, {
            header: t('monthly_price'),
            dataIndex: 'month_payed',
            flex: 2
        }, {
            header: t('reservationdate'),
            renderer: Helper.Util.formatDateTime,
            dataIndex: 'reservationdate',
            flex: 5
        }, {
            header: t('releasedate'),
            renderer: Helper.Util.formatDateTime,
            dataIndex: 'releasedate',
            flex: 5
        }]
        me.callParent(arguments);
    }
});