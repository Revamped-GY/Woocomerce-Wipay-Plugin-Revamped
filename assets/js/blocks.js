( function() {
    if ( ! window.wc || ! window.wc.wcSettings ) {
        return;
    }

    var blocksRegistry = window.wc.wcBlocksRegistry || window.wc.blocksRegistry || null;
    if ( ! blocksRegistry || ! blocksRegistry.registerPaymentMethod ) {
        return;
    }

    var registerPaymentMethod = blocksRegistry.registerPaymentMethod;
    var getSetting = window.wc.wcSettings.getSetting;

    var settings = getSetting( 'rgy_wipay_data', {} );

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && i18n.__ ? i18n.__ : function( s ) { return s; };

    var htmlEntities = window.wp && window.wp.htmlEntities ? window.wp.htmlEntities : null;
    var decodeEntities = htmlEntities && htmlEntities.decodeEntities ? htmlEntities.decodeEntities : function( s ) { return s; };

    var labelText = decodeEntities( settings.title || 'Credit or Debit Card (WiPay)' );
    var description = decodeEntities( settings.description || '' );
    var icons = settings.icons || [];

    var el = window.wp && window.wp.element ? window.wp.element.createElement : null;
    if ( ! el ) {
        return;
    }

    var Label = function() {
        return el( 'span', null, labelText );
    };

    var Content = function() {
        return el( 'div', null,
            el( 'span', null, description ),
            el( 'br' ),
            el( 'small', { style: { opacity: 0.7 } }, __( 'You will be redirected to WiPay to complete payment.', 'revamped-wipay-woocommerce' ) )
        );
    };

    registerPaymentMethod( {
        name: 'rgy_wipay',
        label: el( Label, null ),
        ariaLabel: labelText,
        content: el( Content, null ),
        edit: el( Content, null ),
        canMakePayment: function() {
            return true;
        },
        icons: icons,
        supports: {
            features: settings.supports || [],
        },
    } );
} )();
