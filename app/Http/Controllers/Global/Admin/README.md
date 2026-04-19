# Global Admin Controllers

There are times when the same controller may be needed in both the Central and Global applications. 
In these cases, the controller should be created as an *abstract class* in the Global namespace and extended in the Central application.

The reason for this is that if the same controller file was used in both scopes, Wayfinder would generate multiple routes for each controller which would prevent e.g.
`ProfileController.store.form()` and instead require `ProfileController.store['/settings/profile'].form()`,
or similar - which is not a great developer experience.

This is the case for any 'duplicate' controllers that need to be accessed on the frontend.
