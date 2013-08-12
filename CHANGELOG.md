SBTS CF Uploader Version History
================================

Date		Version	Explanation
----		-------	-----------
20130809	0.2.2	Allow faster return of containers through caching
20130801	0.2.1	Added remove_filter for two Root Relative filters that caused Insert into Post to return wrong
20130723	0.2.0	Migration to php-opencloud API
					Added Wordpress upload/sideload option
					Added jpg to permitted file types
20130506	0.1.5	Added custom functionality to store the duration of audio/mpeg files in CF metadata
					Added single get_file function for other plugins to use
					Improved function structure
20130502	0.1.4	Allowed uploads to execute as long as needed
					Fix for plugin_dir_url php notice
					Cosmetic language change, from images to files on template
20130430	0.1.3	Fixed upload after quick fix for file types (0.1.2 broke it and copy/paste)
					Fixed copy and paste via hack to ZeroClipboard instance on mouseenter
					Improved download links with download attribute in anchor tag
					Fixed fallback for pre-drag-and-drop (or FileReader) browsers
					Fixed delete so the file listing was removed upon successful delete
20130429	0.1.2	Temp fix for uploads so Firefox could do upload; fix to ZeroClipboard to reposition Copy button
20130318	0.1.1	Fixed bug: make sure we anticipate another plugin using Cloud Files' php api
20130315	0.1.0	Initial use: upload (single and multiple), list, delete, switch containers all working
