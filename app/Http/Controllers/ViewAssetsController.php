<?php

namespace App\Http\Controllers;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Models\CustomField;
use App\Models\RequestedAsset;
use App\Notifications\RequestAssetCancelation;
use App\Notifications\RequestAssetNotification;
use App\Notifications\RequestAssetApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\Helper;
use DB;

/**
 * This controller handles all actions related to the ability for users
 * to view their own assets in the Snipe-IT Asset Management application.
 *
 * @version    v1.0
 */
class ViewAssetsController extends Controller
{
    /**
     * Redirect to the profile page.
     *
     * @return Redirect
     */
    public function getIndex()
    {
        $user = User::with(
            'assets',
            'assets.model',
            'assets.model.fieldset.fields',
            'consumables',
            'accessories',
            'licenses',
        )->find(Auth::user()->id);

        $field_array = array();

        // Loop through all the custom fields that are applied to any model the user has assigned
        foreach ($user->assets as $asset) {

            // Make sure the model has a custom fieldset before trying to loop through the associated fields
            if ($asset->model->fieldset) {

                foreach ($asset->model->fieldset->fields as $field) {
                    // check and make sure they're allowed to see the value of the custom field
                    if ($field->display_in_user_view == '1') {
                        $field_array[$field->db_column] = $field->name;
                    }
                    
                }
            }

        }

        // Since some models may re-use the same fieldsets/fields, let's make the array unique so we don't repeat columns
        array_unique($field_array);

        if (isset($user->id)) {
            return view('account/view-assets', compact('user', 'field_array' ))
                ->with('settings', Setting::getSettings());
        }

        // Redirect to the user management page
        return redirect()->route('users.index')
            ->with('error', trans('admin/users/message.user_not_found', $user->id));
    }

    /**
     * Returns view of requestable items for a user.
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getRequestableIndex()
    {
        $assets = Asset::with('model', 'defaultLoc', 'location', 'assignedTo', 'requests')->Hardware()->RequestableAssets();
        $models = AssetModel::with('category', 'requests', 'assets')->RequestableModels()->get();

        return view('account/requestable-assets', compact('assets', 'models'));
    }

    public function getRequestItem(Request $request, $itemType, $itemId = null, $cancel_by_admin = false, $requestingUser = null)
    {
        $item = null;
        $fullItemType = 'App\\Models\\'.studly_case($itemType);

        if ($itemType == 'asset_model') {
            $itemType = 'model';
        }
        $item = call_user_func([$fullItemType, 'find'], $itemId);

        $user = Auth::user();

        $logaction = new Actionlog();
        $logaction->item_id = $data['asset_id'] = $item->id;
        $logaction->item_type = $fullItemType;
        $logaction->created_at = $data['requested_date'] = date('Y-m-d H:i:s');

        if ($user->location_id) {
            $logaction->location_id = $user->location_id;
        }
        $logaction->target_id = $data['user_id'] = Auth::user()->id;
        $logaction->target_type = User::class;

        $data['item_quantity'] = $request->has('request-quantity') ? e($request->input('request-quantity')) : 1;
        $data['requested_by'] = $user->present()->fullName();
        $data['item'] = $item;
        $data['item_type'] = $itemType;
        $data['target'] = Auth::user();

        if ($fullItemType == Asset::class) {
            $data['item_url'] = route('hardware.show', $item->id);
        } else {
            $data['item_url'] = route("view/${itemType}", $item->id);
        }

        $settings = Setting::getSettings();

        if (($item_request = $item->isRequestedBy($user)) || $cancel_by_admin) {
            $item->cancelRequest($requestingUser);
            $data['item_quantity'] = ($item_request) ? $item_request->qty : 1;
            $logaction->logaction('request_canceled');

            if (($settings->alert_email != '') && ($settings->alerts_enabled == '1') && (! config('app.lock_passwords'))) {
                $settings->notify(new RequestAssetCancelation($data));
            }

            return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.canceled'));
        } else {
            $item->request();
            if (($settings->alert_email != '') && ($settings->alerts_enabled == '1') && (! config('app.lock_passwords'))) {
                $logaction->logaction('requested');
                $settings->notify(new RequestAssetNotification($data));
            }

            return redirect()->route('requestable-assets')->with('success')->with('success', trans('admin/hardware/message.requests.success'));
        }
    }

    /**
     * Process a specific requested asset
     * @param null $assetId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getRequestAsset($assetId = null)
    {
		$requestedAsset = new RequestedAsset;
        $user = Auth::user();

        // Check if the asset exists and is requestable
        if (is_null($asset = Asset::RequestableAssets()->find($assetId))) {
            return redirect()->route('requestable-assets')
                ->with('error', trans('admin/hardware/message.does_not_exist_or_not_requestable'));
        }
        if (! Company::isCurrentUserHasAccess($asset)) {
            return redirect()->route('requestable-assets')
                ->with('error', trans('general.insufficient_permissions'));
        }
		

        $data['item'] = $asset;
        $data['target'] = Auth::user();
        $data['item_quantity'] = 1;
        $settings = Setting::getSettings();
		$data['note'] = e(Input::get('note'));

        $logaction = new Actionlog();
        $logaction->item_id = $data['asset_id'] = $asset->id;
        $logaction->item_type = $data['item_type'] = Asset::class;
        $logaction->created_at = $data['requested_date'] = date('Y-m-d H:i:s');

        if ($user->location_id) {
            $logaction->location_id = $user->location_id;
        }
        $logaction->target_id = $data['user_id'] = Auth::user()->id;
        $logaction->target_type = User::class;

        // If it's already requested, cancel the request.
        if ($asset->isRequestedBy(Auth::user())) {
            $asset->cancelRequest();
            $asset->decrement('requests_counter', 1);

            $logaction->logaction('request canceled');
            $settings->notify(new RequestAssetCancelation($data));

            return redirect()->route('requestable-assets')
                ->with('success')->with('success', trans('admin/hardware/message.requests.canceled'));
        }
		
		// double check if the checkout date is outside of any other reservation
        $requests =  DB::table('requested_assets')->where('asset_id',$assetId)->where('expected_checkout', '>=', date('Y-m-d'))->where('request_state', '<','2')->select('expected_checkout','expected_checkin')->get();
 
        $datas = "";
		
		$checkOk = true;
            foreach($requests as &$request)
            {
                $datetime = strtotime($request->expected_checkout);
                $checkout = date('Y-m-d H',$datetime);
                $datetime = strtotime($request->expected_checkin);
                $checkin = date('Y-m-d H',$datetime);
                $checkout_test = strtotime(e(Input::get('checkout_at')));
                $checkout_now = date('Y-m-d H',$checkout_test);        
            

            if ($checkout_now >= $checkout && $checkout_now <= $checkin)
                {
                    // return redirect()->back() ->with('alert',$checkin);
                        return redirect()->route('requestable-assets')
                        ->with('error', trans('admin/hardware/message.dateOverlap'))->with('2');  
                    }
            } 
		if (e(Input::get('checkout_at')) == null || e(Input::get('expected_checkin')) == null)
        {
            return redirect()->route('requestable-assets')
            ->with('error', trans('admin/hardware/message.no_dates'));  
        }

        if (e(Input::get('checkout_at')) == (e(Input::get('expected_checkin'))))
        {
            return redirect()->route('requestable-assets')
            ->with('error', trans('admin/hardware/message.equal_dates'));  

        }
		
		$requestedAsset->asset_id = $asset->id;
        $requestedAsset->checkout_requests_id = CheckoutRequest::all()->last()->id;
        $requestedAsset->user_id = $data['user_id'] = Auth::user()->id;
        $requestedAsset->notes = e(Input::get('note'));    
        $requestedAsset->created_at = $data['requested_date'] = date("Y-m-d H:i:s");
		$requestedAsset->expected_checkout =  e(Input::get('checkout_at'));
        $requestedAsset->expected_checkin = e(Input::get('expected_checkin'));
		
		$requestedAsset->save();


        $logaction->logaction('requested');
        //$asset->request();
        //$asset->increment('requests_counter', 1);
        //$settings->notify(new RequestAssetNotification($data));

        return redirect()->route('requestable-assets')->with('success')->with('success', trans('admin/hardware/message.requests.success'));
    }
	
	
	    public function getRequestView($assetId = null)
    {
        // Isto pode voltar para a outra função
       // return view('hardware/requestout', compact('asset'));
	   $settings = Setting::getSettings();
       $logaction = new Actionlog();
       $logaction->item_id = $data['asset_id'] = $assetId;
       $logaction->item_type = $data['item_type'] = Asset::class;
       $logaction->created_at = $data['requested_date'] = date("Y-m-d H:i:s");

       $user = Auth::user();

       // Check if the asset exists and is requestable
       if (is_null($asset = Asset::RequestableAssets()->find($assetId))) {
           return redirect()->route('requestable-assets')
               ->with('error', trans('admin/hardware/message.does_not_exist_or_not_requestable'));
       } elseif (!Company::isCurrentUserHasAccess($asset)) {
           return redirect()->route('requestable-assets')
               ->with('error', trans('general.insufficient_permissions'));
       }

       $data['item'] = $asset;
       $data['target'] =  Auth::user();
       $data['item_quantity'] = 1;
       $settings = Setting::getSettings();

       //return view('hardware/checkout', compact('asset'));
			
       //return view('hardware/requestout', compact('asset'));
                //->with('statusLabel_list', Helper::deployableStatusLabelList());

       //$logaction->logaction('requested');
       //$asset->request();
       //$asset->increment('requests_counter', 1);
         //$settings->notify(new RequestAssetNotification($data));

       //return $logaction->item_id;
			
		if ($asset->isRequestedBy(Auth::user())) {

        $asset->cancelRequest();
        $asset->decrement('requests_counter', 1);


        // GRIFU | Modification. This function should be transposed to Requestable.php
        $requestedAsset = new RequestedAsset;
        $requestedAsset->where('checkout_requests_id',CheckoutRequest::all()->last()->id)->update(array('request_state' => '3'));

        $logaction->logaction('request canceled');
        $settings->notify(new RequestAssetCancelationNotification($data));
        return redirect()->route('requestable-assets')
            ->with('success')->with('success', trans('admin/hardware/message.requests.cancel-success'));

    } else {
        // GRIFU | Modification
        // retrieve groups that are able to aprove reservations
        // $search = '"assets.responsible":"1"';
        // $userResponsibleGroup =  DB::table('groups')->where('permissions', 'LIKE', '%'.$search.'%')->pluck('id');
        // Get User ID's
        // $userResponsibleIDs =  DB::table('users_groups')->whereIn('group_id', $userResponsibleGroup)->pluck('user_id');

        // Should not access directly to table, please change to model
        // need to check if there is a inbetween reservation for today
        $store =  DB::table('requested_assets')->where('asset_id',$assetId)->where('expected_checkin', '>=', date('Y-m-d'))->where('request_state', '<','2')->select('expected_checkout','expected_checkin')->get();


        // Function to retrieve the allocated date and to add to the reservations preventing the reservation in a date that the object is allocated        
        if ($asset->expected_checkin !=null) {

            $expected = $asset->where('id',$assetId)->select('last_checkout','expected_checkin')->get();
            $tmp = date($expected[0]->expected_checkin);
            unset($expected[0]->expected_checkin);
            $expected[0]->expected_checkout = date($expected[0]->last_checkout);
            unset($expected[0]->last_checkout);
            $expected[0]->expected_checkin = $tmp;

            $store->push($expected[0]); // It adds to the vector the expected chekin date

        }

        // Send a list of responsible users and reservation dates to view requestout.blade
        // return View::make('hardware/requestout', compact('asset'))->with('store', $store)->with('Responsibles', $userResponsibleIDs);
        return view('hardware/requestout', compact('asset'))->with('store', $store);

    }          

    }

    function extend($obj, $obj2) {
        $vars = get_object_vars($obj2);
        foreach ($vars as $var => $value) {
            $obj->$var = $value;
        }
        return $obj;
    }
	
	// Function to request approval
// GRIFU | Modification
	public function requestAssetApproval($requestId  = null)
	{
    	$requestedAsset = new RequestedAsset;
    	$user = Auth::user();
    	return 'request ID ='.$requestId;
	}

    public function getRequestedAssets()
    {
        return view('account/requested');
    }
	
	
}
