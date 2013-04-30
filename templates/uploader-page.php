<div class="wrap sbts-cf-uploader">
    <h2>Cloud Files Uploader</h2>
	<div class="file-upload">
		<div class="instruct"><p>Drag images here to upload</p></div>
		<div class="space-age" id="holder">
			<div class="activity-response"></div>
		</div>
		<div class="fallbacks">
			<label>Drag &amp; drop not supported, but you can still upload via this input field:<input id="old_upload" name="file[]" type="file" multiple="multiple" /></label>
			<div class="activity-response"></div>
		</div>
		<div class="meter orange" id="ajax_progress"><span style="width:0%;"></span></div>
		<label for="sbts_cf_uploader_container">Cloud Files Container</label>
		<select id="sbts_cf_uploader_container"></select>
		<div class="path-wrap">
			<p id="path_display">Upload path: <span id="full_path_path"></span></p>
			<input id="additional_path" value="" />
			<p>File name of upload will be used for the file name.</p>
		</div>
	</div>
	<div class="file-management">
		<div class="instruct"><p>Currently uploaded files</p></div>
		<div class="file-list"></div>
	</div>
	<script id="file_up_tmpl" type="text/x-handlebars-template">
		<li class="file-listing-wrap">
			<div class="file-listing">
				<span class="file-name-label" for="file_name">{{full_name}} ({{type}}) {{size}} ({{last_mod}})</span>
				<button class="delete-button" data-file_name="{{full_name}}">Delete</button>
				<a download href="{{uri}}">Download</a>
			</div>
		</li>
	</script>
	<script id="tree_tmpl" type="text/x-handlebars-template">
		<li id="{{path_id}}" class="path-choice">
			<input id={{sel_id}} class="path-select" type="radio" name="existing_path" data-existing_path="{{path}}" />
			&nbsp;<label for="{{sel_id}}">{{path}}</label>
			<ul class="sub-tree"></ul>
		</li>
	</script>
</div>
