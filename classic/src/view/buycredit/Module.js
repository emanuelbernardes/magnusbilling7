/**
 * Classe que define o panel de "Boleto"
 *
 * MagnusSolution.com <info@magnussolution.com>
 * 17/08/2012
 */
Ext.define('MBilling.view.buycredit.Module', {
    extend: 'Ext.form.Panel',
    alias: 'widget.buycreditmodule',
    controller: 'buycredit',
    resizable: false,
    autoShow: true,
    header: false,
    initComponent: function() {
        var me = this;
        me.items = [{
            reference: 'buycreditPanel',
            xtype: 'form',
            margin: '10 10 10 10',
            autoShow: true,
            closable: false,
            resizable: false,
            bodyPadding: 10,
            defaultType: 'textfield',
            defaults: {
                labelAlign: 'right',
                labelWidth: 150,
                width: 280,
                allowBlank: false,
                msgTarget: 'side',
                enableKeyEvents: true,
                plugins: 'markallowblank',
                anchor: '100%'
            },
            items: [{
                xtype: 'moneyfield',
                mask: App.user.currency + ' #9.999.990,00',
                fieldLabel: t('Amounts'),
                value: 0,
                name: 'amount'
            }, {
                xtype: 'methodpaycombo',
                fieldLabel: t('paymentmethods'),
                name: 'method'
            }, {
                fieldLabel: t('CreditCard Number'),
                name: 'card_num',
                hidden: true
            }, {
                xtype: 'datefield',
                fieldLabel: t('Expiration Date'),
                name: 'exp_date',
                format: 'm/y',
                hidden: true
            }],
            bbar: [{
                text: t('Cancel'),
                tooltip: t('Cancel'),
                glyph: me.glyphCancel,
                handler: 'buyCreditClose',
                hidden: true,
                reference: 'btnCancel'
            }, {
                text: t('Next'),
                tooltip: t('Next'),
                width: 100,
                glyph: icons.disk,
                handler: 'buyCredit'
            }]
        }];
        me.callParent(arguments);
    }
});