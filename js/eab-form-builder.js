( function( $ ) {
    
    var Builder = {
        
        counter: $( '#eab_rsvp_id_flag' ).val(),
        
        init: function() {
            $( document ).on( 'click', '.eab_element', this.render_element_to_canvas );
            $( document ).on( 'click', '.eab_element_rendered h4', this.run_inner_accordion );
            $( document ).on( 'click', '.eab_element_rendered h4 span', this.remove_element_panel );
        },
        
        render_element_to_canvas: function() {
            
            var elementType = $( this ).data( 'option' );
            
            _.templateSettings.variable = 'data';
            var template = _.template(
                $( '.eab_form_template_' + elementType ).html()
            );
            
            var templateData = {
                uniqueID: 'eab_element_id_' + Builder.counter++
            };
            
            $( '.eab_form_stage' ).append(
                template( templateData )
            );
            
            $( '#' + templateData.uniqueID ).find( 'h4' ).click();
            
        },
        
        run_inner_accordion: function() {
            
            var obj = $( this ).parent(),
                elem = obj.find( '.eab_element_rendered_box' );
            
            if( elem.hasClass( 'eab_visible' ) ) {
                elem.slideUp().removeClass( 'eab_visible' );
            }
            else {
                elem.slideDown().addClass( 'eab_visible' );
            }
        },
        
        remove_element_panel: function() {
            
            $( this ).closest( '.eab_element_rendered' ).remove();
            
        }
        
    };
    
    Builder.init();
    
    
    
    var RSVP = {
        
        init: function() {
            
        }
        
    };
    
    RSVP.init();
    
} )( jQuery );