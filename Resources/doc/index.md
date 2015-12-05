Trustify Mass Update Extension
==============================

How to enable Mass Update
-------------------------

This feature provides a way to mass edit entities, let’s say Contacts or Cases.

To enable this feature for any entity you need to go to top level menu
System -> Entities -> Entity Management -> select some entity to edit -> find checkbox "Is Mass Update Action Enabled” on the page and check it to enable -> and then press "Save and Close” green button on top right corner.

How to Use Mass Update
----------------------

On the entity list (grid) page for the entity the feature was enabled for, you'll see checkboxes on each row. Select a few entities and on the top right corner of the grid you'll see a gear button, by pressing on it - you'll see available mass actions, one of them - "Bulk Edit".

When pressing on that drop down item, a dialog window (not browser real window, just an overlay) should appear with one select input, select some field for the entity, like Subject (for Case) and new input will be loaded dynamically. Put some value into that second input and press "Perform" blue button. Window should disappear and action should be performed, and grid should reload itself and green success message should be displayed on top of the page, right below the top menu.

How to change field types in update dialog
------------------------------------------

While rendering input for updatable field in dialog, system tries to guess its Symfony Form type.

For extended fields it's done automatically based on their 'extend type', but for regular fields, there are two options:
 
 * Use form scope of Entity Configuration (in such case that entity's field will always be rendered with corresponding form type, system-wide)
 
    According to [EntityExtendBundle](https://github.com/orocrm/platform/tree/master/src/Oro/Bundle/EntityExtendBundle#modify-existing-entity) documentation and 
    [exampe with oro options](https://github.com/orocrm/platform/blob/master/src/Oro/Bundle/EntityExtendBundle/Resources/doc/custom_form_type.md#using-annotation-to-field-or-related-entity-if-extended-field-is-a-relation), 
    where under oro_options it's possible to define system-wide form_type and form_option for some entity's field.
    
    *Note*: This works only if entity is configurable.
    
 * Use app/config/config.yml or Resources/config/oro/app.yml in any other bundle (with higher priority than MassUpdateBundle to override default mapping located in MassUpdateBundle/Resources/config/oro/app.yml
