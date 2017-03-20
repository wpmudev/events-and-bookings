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
            if( eabRSVP.logged_in == 1 )
            {
                $( document ).on( 'click', '.wpmudevevents-yes-submit', this.appear_custom_fields );
                $( document ).on( 'click', '.eab_rsvp_form_submit', this.vefiry_form );
            }
        },
        
        appear_custom_fields: function( e ) {
            e.preventDefault();
            
            $( this ).next( '.eab_rsvp_custom_form' ).slideDown();
            
            return false;
        },
        
        vefiry_form: function( e ) {
            e.preventDefault();
            
            var parentClass = '.eab_field_required',
                fieldCol = '.eab_field_col',
                hasError = false;
                
            $( parentClass ).each(function() {
                var objCol = $(this).find( fieldCol );
                
                if( objCol.find( 'input' ).length > 0 )
                {
                    var elem = objCol.find( 'input' );
                    type = elem.attr( 'type' );
                    if( type == 'text' )
                    {
                        if( elem.val() === '' ) {
                            hasError = true;
                        }
                    }
                    else if( type == 'checkbox' )
                    {
                        hasError = true;
                        elem.closest( fieldCol ).find( 'input[type="checkbox"]' ).each(function() {
                            if( $(this).is( ':checked' ) ){alert(123);
                                hasError = false;
                                return;
                            }
                        });
                    }
                    else if( type == 'radio' )
                    {
                        hasError = true;
                        elem.closest( fieldCol ).find( 'input[type="radio"]' ).each(function() {
                            if( $(this).is( ':checked' ) ){
                                hasError = false;
                                return;
                            }
                        });
                    }
                }
                
                /*if( objCol.find( 'input[type="text"]' ).length )
                {
                    field = 'text';
                }
                else if( objCol.find( 'input[type="checkbox"]' ) )
                {
                    field = 'checkbox';
                }
                else if( objCol.find( 'input[type="radio"]' ).length )
                {
                    field = 'radio';
                }
                else if( objCol.find( 'select' ).length )
                {
                    field = 'dropdown';
                }
                else if( objCol.find( 'textarea' ).length )
                {
                    field = 'textarea';
                }
                
                switch( field ) {
                    case 'text':
                        if( objCol.find( 'input[type="text"]' ).val() == '' )
                        {alert(123);
                            hasError = true;
                        }
                        break;
                    
                    case 'checkbox':
                        hasError = true;
                        objCol.find( 'input[type="checkbox"]' ).each(function() {
                            if( $( this ).is( ':checked' ) )
                            {alert(1234);
                                hasError = false;
                                
                            }
                        });
                        break;
                    
                    case 'radio':
                        if( objCol.find( 'input[type="radio"]:checked' ).val() == '' )
                        {alert(1237);
                            hasError = true;
                        }
                        break;
                    
                    case 'dropdown':
                        if( objCol.find( 'select' ).val() == '' )
                        {alert(129);
                            hasError = true;
                        }
                        break;
                    
                    case 'textarea':
                        if( objCol.find( 'textarea' ).val() == '' )
                        {alert(12322);
                            hasError = true;
                        }
                        break;
                }*/
            });
            
            console.log(hasError);
            
            return false;
        }
        
    };
    
    RSVP.init();
    
} )( jQuery );