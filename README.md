# Wordpress Image Size

A plugin that disables the default resizing of images on upload and creates an endpoint for getting images.

The plugin defines the endpoint /image/{attachment slug}/ which returns the original file.
The endpoint /image/{attachment slug}/{width}x{height}/ returns the resized file.

Files are being resized on the first call made to the endpoint.