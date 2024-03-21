<?php

namespace App\Http\Requests;

use App\Models\SnipeModel;
use enshrined\svgSanitize\Sanitizer;
use Intervention\Image\Facades\Image;
use App\Http\Traits\ConvertsBase64ToFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exception\NotReadableException;


class ManualUploadRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $max_file_size = \App\Helpers\Helper::file_upload_max_size();

        return [
          'file.*' => 'required|mimes:png,gif,jpg,svg,jpeg,doc,docx,pdf,txt,zip,rar,xls,xlsx,lic,xml,rtf,json,webp|max:'.$max_file_size,
        ];
    }

    public function response(array $errors)
    {
        return $this->redirector->back()->withInput()->withErrors($errors, $this->errorBag);
    }

    /**
     * Handle and store any images attached to request
     * @param SnipeModel $item Item the image is associated with
     * @param string $path  location for uploaded images, defaults to uploads/plural of item type.
     * @return SnipeModel        Target asset is being checked out to.
     */
	
	/*public function handleFile(string $dirname, string $name_prefix, $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $file_name = $name_prefix.'-'.str_random(8).'-'.str_slug(basename($file->getClientOriginalName(), '.'.$extension)).'.'.$file->guessExtension();


        \Log::debug("Your filetype IS: ".$file->getMimeType());
        // Check for SVG and sanitize it
        if ($file->getMimeType() === 'image/svg+xml') {
            \Log::debug('This is an SVG');
            \Log::debug($file_name);

            $sanitizer = new Sanitizer();
            $dirtySVG = file_get_contents($file->getRealPath());
            $cleanSVG = $sanitizer->sanitize($dirtySVG);

            try {
                Storage::put($dirname.$file_name, $cleanSVG);
            } catch (\Exception $e) {
                \Log::debug('Upload no workie :( ');
                \Log::debug($e);
            }

        } else {
            $put_results = Storage::put($dirname.$file_name, file_get_contents($file));
            \Log::debug("Here are the '$put_results' (should be 0 or 1 or true or false or something?)");
        }
        return $file_name;
    }*/
	
	
	
    public function handleManuals($item, $form_fieldname = 'manual', $path = null, $db_fieldname = 'manual')
    {

        $type = strtolower(class_basename(get_class($item)));

        if (is_null($path)) {

            $path = str_plural($type);

            if ($type == 'assetmodel') {
                $path = 'models';
            }
        }

        if ($this->offsetGet($form_fieldname) instanceof UploadedFile) {
           $manual = $this->offsetGet($form_fieldname);
           \Log::debug('Image is an instance of UploadedFile');
        } elseif ($this->hasFile($form_fieldname)) {
            $manual = $this->file($form_fieldname);
            \Log::debug('Just use regular upload for '.$form_fieldname);
        } else {
            \Log::debug('No manual found for form fieldname: '.$form_fieldname);
        }

        if (isset($manual)) {

            if (!config('app.lock_passwords')) {

                $ext = $manual->guessExtension();
                $file_name = $type.'-'.$form_fieldname.'-'.$item->id.'-'.str_random(10).'.'.$ext;

                \Log::info('File name will be: '.$file_name);
                \Log::debug('File extension is: '.$ext);

                    \Log::debug('Not an SVG or webp - resize');
                    \Log::debug('Trying to upload to: '.$path.'/'.$file_name);


                    // This requires a string instead of an object, so we use ($string)
                    Storage::disk('public')->put($path.'/'.$file_name, (string) $upload->encode());

                

                 // Remove Current manual if exists
                if (($item->{$form_fieldname}!='') && (Storage::disk('public')->exists($path.'/'.$item->{$db_fieldname}))) {
                    \Log::debug('A file already exists that we are replacing - we should delete the old one.');
                    try {
                         Storage::disk('public')->delete($path.'/'.$item->{$form_fieldname});
                         \Log::debug('Old file '.$path.'/'.$file_name.' has been deleted.');
                    } catch (\Exception $e) {
                        \Log::debug('Could not delete old file. '.$path.'/'.$file_name.' does not exist?');
                    }
                }

                $item->{$db_fieldname} = $file_name;
            }


        // If the user isn't uploading anything new but wants to delete their old manual, do so
        } elseif ($this->input('manual_delete') == '1') {
            \Log::debug('Deleting manual');
            try {
                Storage::disk('public')->delete($path.'/'.$item->{$db_fieldname});
                    $item->{$db_fieldname} = null;
            } catch (\Exception $e) {
                \Log::debug($e);
            }

        }

        return $item;
    }
    
}
