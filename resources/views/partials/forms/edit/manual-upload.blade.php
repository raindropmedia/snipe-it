<!-- Image stuff - kept in /resources/views/partials/forms/edit/manual-upload.blade.php -->
<!-- Image Delete -->
@if (isset($item) && ($item->manual) && ($item->manual!=''))
    <div class="form-group{{ $errors->has('manual_delete') ? ' has-error' : '' }}">
        <div class="col-md-9 col-md-offset-3">
            <label class="form-control">
                {{ Form::checkbox('manual_delete', '1', old('manual_delete'), ['aria-label'=>'manual_delete']) }}
                {{ trans('general.user_manual_delete') }}
                {!! $errors->first('manual_delete', '<span class="alert-msg">:message</span>') !!}
            </label>
        </div>
    </div>
    <div class="form-group">
        <div class="col-md-9 col-md-offset-3">
            <a href="{{ Storage::disk('public')->url($manual_path.e($item->manual)) }}" class="btn btn-sm btn-default" target="_blank">
                                                            <i class="fa fa-download" aria-hidden="true"></i>
                                                        </a>
            {!! $errors->first('manual_delete', '<span class="alert-msg">:message</span>') !!}
        </div>
    </div>
@endif




<!-- Image Upload and preview -->

<div class="form-group {{ $errors->has((isset($fieldname) ? $fieldname : 'manual')) ? 'has-error' : '' }}">
    <label class="col-md-3 control-label" for="{{ (isset($fieldname) ? $fieldname : 'manual') }}">{{ trans('general.user_manual_upload') }}</label>
    <div class="col-md-9">

        <input type="file" id="{{ (isset($fieldname) ? $fieldname : 'manual') }}" name="{{ (isset($fieldname) ? $fieldname : 'manual') }}" aria-label="{{ (isset($fieldname) ? $fieldname : 'manual') }}" class="sr-only">

        <label class="btn btn-default" aria-hidden="true">
            {{ trans('button.select_file')  }}
            <input type="file" name="{{ (isset($fieldname) ? $fieldname : 'manual') }}" class="js-uploadFile" id="uploadFile2" data-maxsize="{{ Helper::file_upload_max_size() }}" accept="application/pdf,image/jpeg,image/png" style="display:none; max-width: 90%" aria-label="{{ (isset($fieldname) ? $fieldname : 'manual') }}" aria-hidden="true">
        </label>
        <span class='label label-default' id="uploadFile2-info"></span>

        <p class="help-block" id="uploadFile2-status">{{ trans('general.manual_filetypes_help', ['size' => Helper::file_upload_max_size_readable()]) }}</p>
        {!! $errors->first('manual', '<span class="alert-msg" aria-hidden="true">:message</span>') !!}
    </div>
    <div class="col-md-4 col-md-offset-3" aria-hidden="true">
        <img id="uploadFile2-manualPreview" style="max-width: 300px; display: none;" alt="{{ trans('general.alt_uploaded_manual_thumbnail') }}">
    </div>
</div>

