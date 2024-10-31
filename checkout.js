const settings_paykeeper = window.wc.wcSettings.getSetting( 'paykeeper_data', {} );
const label_paykeeper = window.wp.htmlEntities.decodeEntities( settings_paykeeper.title ) || window.wp.i18n.__( 'Paykeeper', 'paykeeper' );
const Content_paykeeper = () => {
    return window.wp.htmlEntities.decodeEntities( settings_paykeeper.description || '' );
};
const Block_Gateway_Paykeeper = {
    name: 'paykeeper',
    label: label_paykeeper,
    content: Object( window.wp.element.createElement )( Content_paykeeper, null ),
    edit: Object( window.wp.element.createElement )( Content_paykeeper, null ),
    canMakePayment: () => true,
    ariaLabel: label_paykeeper,
    supports: {
        features: settings_paykeeper.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway_Paykeeper );