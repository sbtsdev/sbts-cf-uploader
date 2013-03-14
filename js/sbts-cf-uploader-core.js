(function ($) {
	var holder = document.getElementById('holder'),
		tests = {
			filereader: typeof FileReader != 'undefined',
			dnd: 'draggable' in document.createElement('span'),
			formdata: !!window.FormData,
			progress: "upload" in new XMLHttpRequest()
		},
		// TODO load valid types via ajax call to wordpress
		accepted_type = {
			'image/png'		: true,
			'image/jpeg'	: true,
			'image/gif'		: true,
			'audio/mpeg'	: true,
			'audio/mp3'		: true,
			'video/x-flv'	: true,
			'video/mp4'		: true
		},
		tmpl_jq = '#file_up_tmpl',
		tmpl_st_jq = '#tree_tmpl',
		template = {
			'file_up'	: Handlebars.compile($(tmpl_jq).html()),
			'sub_tree'	: Handlebars.compile($(tmpl_st_jq).html())
		},
		containers = [],
		files = [],
		tree_delimeter = '/',
		tree = {},
		tree_root_id = 'sbts_cf_tree',
		tree_root_jq = '#' + tree_root_id,
		path_id_pre = 'sbts_cf_path_id_',
		path_select_id_pre = 'path_select_',
		path_display_jq = '#path_display',
		full_path_jq = '#full_path_path',
		user_added_path_jq = '#additional_path',
		error_no_path = false,
		sub_tree_jq = '.sub-tree',
		cont_pick_jq = '#sbts_cf_uploader_container',
		file_list_jq = '.file-list',
		message_jq = '.activity-response',
		error_class = 'error-text',
		progress_bar_jq = '#ajax_progress>span';

	function progress_handler(e){
		var perc = e.total / e.loaded * 100;
		if(e.lengthComputable){
			$(progress_bar_jq).css('width', perc + '%');
		}
	}

	function display_files() {
		var i = 0, current_path,
			file_len = files.length,
			file_list = $(file_list_jq);
		file_list.children().remove();
		for (; i < file_len; i += 1) {
			file_list.append(template.file_up(files[i]));
		}
	}

	// use temp_tree recursively to add tree[tree_delimeter].media.audio.Podcast = {'__files':[4,10,18]}
	// pa should be in the form pa = ['media', 'audio', 'Podcast']
	// every listing should have a path and file
	function add_path_to_tree(temp_tree, pa, i) {
		var pa_current = pa.shift();
		if (! temp_tree[pa_current]) {
			if (pa.length > 0) {
				temp_tree[pa_current] = {};
				temp_tree[pa_current] = add_path_to_tree(temp_tree[pa_current], pa, i);
			} else {
				temp_tree[pa_current] = {'__files':[i]};
			}
		} else {
			if (pa.length > 0) {
				temp_tree[pa_current] = add_path_to_tree(temp_tree[pa_current], pa, i);
			} else {
				if (! temp_tree[pa_current].__files) {
					temp_tree[pa_current].__files = [i];
				} else {
					temp_tree[pa_current].__files.push(i);
				}
			}
		}
		return temp_tree;
	}

	function add_files_to_tree(more_files) {
		var i = 0, file_len = more_files.length,
			path, pa, path_len;
		for (; i < file_len; i += 1) {
			path = more_files[i].path || '';
			pa = path.substr(1, path.length - 2).split(tree_delimeter);
			tree = add_path_to_tree(tree, pa, i);
		}
	}

	function load_tree() {
		// TODO figure out how to get tree_delimeter to possibly replace hard-coded '/'
		tree = {};
		tree[tree_delimeter] = {};
		add_files_to_tree(files);
	}

	function get_sub_tree_by_path(path) {
		var i = 0, pa, pa_len, sub_tree = tree;
		if (path !== tree_delimeter) {
			pa = path.substr(1, path.length - 2).split(tree_delimeter);
			pa_len = pa.length;
			for (; i < pa_len; i += 1) {
				sub_tree = sub_tree[pa[i]];
			}
		}
		return sub_tree;
	}

	function display_sub_tree(sub_tree, target, building_path) {
		$.each(sub_tree, function (index, val) {
			var st_obj, // object to pass to the template
				id_post, // what makes the id's unique
				my_path, // keep track of the path structure for this iteraction
				i = 0;
			// display __files, if any
			if ('__files' === index) {
				files_len = val.length;
				for (i = 0; i < files_len; i += 1) {
					target.append(template.file_up(files[val[i]]));
				}
			} else {
				my_path = building_path + (tree_delimeter === index ? index : tree_delimeter === building_path ? index : tree_delimeter + index);
				id_post = my_path.replace(new RegExp(tree_delimeter, 'g'), '_');
				st_obj = {
					'path_id'	: path_id_pre + id_post,
					'sel_id'	: path_select_id_pre + id_post,
					'path'		: my_path
				};
				target.append(template.sub_tree(st_obj));
				display_sub_tree(val, $('#' + st_obj.path_id).children(sub_tree_jq).first(), my_path);
			}
		});
	}

	function display_tree_by_path(path, target) {
		var sub_tree = get_sub_tree_by_path(path);
		display_sub_tree(sub_tree, target, '');
	}

	function display_tree() {
		var file_list = $(file_list_jq);
		file_list.children().remove();
		file_list.append('<ul id="' + tree_root_id + '"></ul>');
		display_tree_by_path(tree_delimeter, file_list.children(tree_root_jq));
	}

	function load_files(f) {
		files = f;
		load_tree();
		display_tree();
		// display_files();
	}

	function display_message(message, error) {
		var msg_wrap = $('<p>').html(message);
		if (error) {
			msg_wrap.addClass(error_class);
		}
		$(message_jq).append(msg_wrap);
	}

	function clear_messages() {
		$(message_jq).children().remove();
	}

	function load_container_picker() {
		var i = 0, sel = $('#sbts_cf_uploader_container'), size, mbgb = 'Mb',
			tmpl = '<option value="{{NAME}}">{{NAME}} ({{COUNT}}) [{{SIZE}}]</option>',
			cl = containers ? containers.length : 0;
		for (; i < cl; i += 1) {
			size = containers[i].bytes / (1024.00 * 1024.00);
			if (Math.floor(size / 1024) > 0) {
				size = size / 1024.00;
				mbgb = 'Gb';
			} else {
				mbgb = 'Mb';
			}
			containers[i].display_size = size.toFixed(2) + ' ' + mbgb;
			sel.append(tmpl.replace(/\{\{NAME\}\}/gi, containers[i].name).replace(/\{\{COUNT\}\}/, containers[i].count).replace(/\{\{SIZE\}\}/, containers[i].display_size));
		}
	}

	function load_containers(conts) {
		containers = conts;
		load_container_picker();
	}

	function get_files(container_to_get) {
		if (container_to_get) {
			$.ajax({
				'url'	: ajaxurl,
				'data'	: {
					'action'		: 'get_files',
					'sbts_cf_auth'	: 'add-later :)',
					'sbts_cf_cont'	: container_to_get
				},
				'dataType'	: 'json',
				'success'	:	function (rjson) {
									if (rjson && rjson.success) {
										display_message(rjson.message, false);
										load_files(rjson.pl);
									} else {
										display_message(rjson.message || "No files returned.", true);
									}
				},
				'error'	:	function (jqXHR, textStatus, errorThrown) {
								display_message(errorThrown, true);
				}
			});
		} else {
			display_message('No containers found.', true);
		}
	}

	function get_containers() {
		clear_messages();
		$.ajax({
			'url'	: ajaxurl,
			'data'	: {
				'action'		: 'get_containers',
				'sbts_cf_auth'	: 'add-later :)'
			},
			'dataType'	:	'json',
			'success'	:	function (rjson) {
								if (rjson && rjson.success) {
									load_containers(rjson.pl);
									display_message(rjson.message, false);
									get_files($(cont_pick_jq).val());
								} else {
									display_message(rjson ? rjson.message || 'No containers available.' : 'Could not connect.', true);
								}
			},
			'error'	:	function (jqXHR, textStatus, errorThrown) {
							display_message(errorThrown, true);
			}
		});
	}

	function upload_files(uploads) {
		var i = 0, uploads_len = uploads.length,
			cont = $(cont_pick_jq).val(),
			path = $(full_path_jq).data('full_path') || '',
			a_tmpl = '<a href="{{URI}}">{{NAME}}</a>',
			form_data = tests.formdata ? new FormData() : null;
		if (tests.formdata && (path.length > 0)) {
			clear_messages();
			for (; i < uploads_len; i+= 1) {
				if (accepted_type[uploads[i].type]) {
					form_data.append('file[]', uploads[i]);
				} else {
					display_message('File type ' + uploads[i].type + ' not permitted.', true);
				}
			}
			form_data.append('action', 'upload_files');
			form_data.append('sbts_cf_auth', 'add-later :)');
			form_data.append('sbts_cf_cont', cont);
			form_data.append('sbts_cf_path', path);

			$(progress_bar_jq).css('width', '0%');
			$.ajax({
				'url'		:	ajaxurl,
				'type'		:	'post',
				'data'		:	form_data,
				'dataType'	:	'json',
				'xhr'		:	function () {
									var myXHR = $.ajaxSettings.xhr();
									if (myXHR.upload) {
										myXHR.upload.addEventListener('progress', progress_handler, false);
									}
									return myXHR;
				},
				'success'	:	function (rjson) {
									var i = 0, up_len;
									$(progress_bar_jq).css('width', '0%');
									if (rjson && rjson.success) {
										display_message(rjson.message, false);
										// TODO important - use load_files or the like to update display files
										for (i = 0; i < rjson.pl.length; i += 1) {
											display_message(a_tmpl.replace(/\{\{URI\}\}/g, rjson.pl[i].uri).replace(/\{\{NAME\}\}/g, rjson.pl[i].name), false);
										}
									} else if (rjson && rjson.success === false) {
										// TODO should probably reload all files by calling get_files so
										//		that if the user tried to upload many files and only a few
										//		made it then they can check them
										//		Same with the 'error' function
										display_message(rjson.message, true);
									}
				},
				'error'		:	function (jqXHR, textStatus, errorThrown) {
									$(progress_bar_jq).css('width', '0%');
									display_message(errorThrown, true);
				},
				'cache': false,
				'contentType': false,
				'processData': false
			});
		} else if (path.length === 0) {
			display_message('Please add upload path.', true);
			$(path_display).addClass(error_class);
			error_no_path = true;
		}
	}

	function delete_files(form_data) {
		if (tests.formdata && (form_data instanceof FormData)) {
			clear_messages();
			form_data.append('action', 'delete_files');
			form_data.append('from', 'admin');
			$.ajax({
				'url'		:	ajaxurl,
				'type'		:	'post',
				'data'		:	form_data,
				'dataType'	:	'json',
				'success'	:	function (rjson) {
									var i = 0;
									if (rjson && rjson.success) {
										display_message(rjson.message, false);
										get_files(display_files);
										if (rjson.files) {
											for(del_len = rjson.files.length; i < del_len; i+= 1) {
												display_message(rjson.files[i].replace('../uploads/', ''), false);
											}
										}
									} else if (rjson && (!rjson.success)) {
										display_message(rjson.message, true);
									}
				},
				'error'		:	function (jqXHR, textStatus, errorThrown) {
									display_message(errorThrown, true);
				},
				'cache'		: false,
				'contentType':false,
				'processData':false
			});
		}
	}

	if ((tests.filereader === false) || (tests.formdata === false) || (tests.dnd === false)) {
		$('.fallbacks').css('display', 'block');
		$('.space-age').css('display', 'none');
	}

	if (tests.dnd) {
		holder.ondragover = function () { this.className = 'hover'; return false; };
		holder.ondragend = function () { this.className = ''; return false; };
		holder.ondrop = function (e) {
			this.className = '';
			e.preventDefault();
			upload_files(e.dataTransfer.files);
		};
	} else {
		document.getElementById('old_upload').onchange = function () {
			upload_files(this.files);
		};
	}
	function path_add_extra(existing, user_added) {
		if (user_added && user_added.substr(0, 1) === tree_delimeter) {
			user_added = user_added.substr(1);
		}
		if (user_added && user_added.substr(user_added.length - 1, 1) === tree_delimeter) {
			user_added = user_added.substr(0, user_added.length - 1);
		}
		user_added = encodeURI(user_added).replace(/%20/g, ' ');
		return (tree_delimeter === existing ? existing : existing + tree_delimeter) + (user_added ? user_added + tree_delimeter : '');
	}
	function update_upload_path(existing_path, user_added) {
		var full_path = path_add_extra(existing_path, user_added);
		$(full_path_jq).data('existing_path', existing_path);
		$(full_path_jq).data('full_path', full_path);
		$(full_path_jq).text(full_path);
		if (error_no_path && (full_path.length > 0)) {
			$(path_display_jq).removeClass(error_class);
			error_no_path = false;
		}
	}
	$(user_added_path_jq).on('keyup', function (e) {
		update_upload_path($(full_path_jq).data('existing_path'), $(this).val() || '');
	});
	$('.file-list').on('change', '.path-select', function (e) {
		update_upload_path($(this).data('existing_path'), $(user_added_path_jq).val() || '');
	});
	$('.file-list').on('click', '.path-choice>label', function (e) {
		$(this).closest('.path-choice').children('.sub-tree').toggle();
	});
	$('.file-list').on('click', '.delete-button', function (e) {
		// TODO add confirm button
		var form_data;
		if (tests.formdata && ($(this).data('file_name').length > 0)) {
			form_data = new FormData();
			form_data.append('file[]', $(this).data('file_name'));
			delete_files(form_data);
		}
	});
	$(cont_pick_jq).on('change', function (e) {
		clear_messages();
		get_files($(cont_pick_jq).val());
	});
	get_containers();
}(jQuery));
