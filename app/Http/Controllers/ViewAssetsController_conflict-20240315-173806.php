<?php

namespace App\Http\Controllers;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Models\CustomField;
use App\Models\CheckoutRequest;
use App\Models\RequestedAsset;
use App\Notifications\RequestAssetCancelation;
use App\Notifications\RequestAssetNotification;
use App\Notifications\RequestAssetApprovalNotification;
use App\Notifications\RequestAssetCancelationNotification;
use App\Notifications\RequestAssetRejectNotification;
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
        //$assets = Asset::with('model', 'defaultLoc', 'location', 'assignedTo', 'requests')->Hardware()->RequestableAssets();
		$assets = Asset::with('model', 'defaultLoc', 'location', 'assignedTo', 'requests')->Hardware()->RequestableAssets();
        $models = AssetModel::with('category', 'requests', 'assets')->RequestableModels()->get();
		
		echo $assets;
		foreach($assets as $asset){
			echo "Blub".$asset."<br>";
		}

        //return view('account/requestable-assets', compact('assets', 'models'));
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
public function getRequestAsset(Request $request, $assetId = null)
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
	
		$converted_checkout_at = Helper::convertToMysqlDatetime($request->input('checkout_at'));
		$converted_expected_checkin = Helper::convertToMysqlDatetime($request->input('expected_checkin'));
		

        $data['item'] = $asset;
        $data['target'] = Auth::user();
        $data['item_quantity'] = 1;
        $settings = Setting::getSettings();
		$data['note'] = $request->input('note');
		$data['check_out'] = $request->input('checkout_at');
        $data['check_in'] = $request->input('expected_checkin');
		$data['fieldset'] = $request->input('fieldset');
        $data['request_id'] = $requestedAsset->id;
		

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
			
			// GRIFU | Modification. This function should be transposed to Requestable.php
        	$requestedAsset->where('checkout_requests_id',CheckoutRequest::all()->last()->id)->update(array('request_state' => '3'));

            $logaction->logaction('request canceled');
            $settings->notify(new RequestAssetCancelation($data));

            return redirect()->route('requestable-assets')
                ->with('success')->with('success', trans('admin/hardware/message.requests.canceled'));
        }
		
		// double check if the checkout date is outside of any other reservation
        $requests2 =  DB::table('requested_assets')->where('asset_id',$assetId)->where('expected_checkout', '>=', date('Y-m-d'))->where('request_state', '<','2')->select('expected_checkout','expected_checkin')->get();
 
        $datas = "";
		
		$checkOk = true;
            foreach($requests2 as &$request2)
            {
				//print_r($request2);
				//echo "<br>";
				
                $checkout_is = new \DateTime(date('Y-m-d H:i:s',strtotime($request2->expected_checkout)));
                $checkin_is = new \DateTime(date('Y-m-d H:i:s',strtotime($request2->expected_checkin)));
                $checkout_now = new \DateTime(date('Y-m-d H:i:s',strtotime($converted_checkout_at))); 
                $checkin_now = new \DateTime(date('Y-m-d H:i:s',strtotime($converted_expected_checkin))); 
				//echo "checkout_is ".$checkout_is."<br>";
				//echo "checkin_is ".$checkin_is."<br>";
				
				//echo "checkout_now ".$checkout_now."<br>";
				//echo "checkin_now ".$checkin_now."<br>";
				
				//echo $checkout_now." >= ".$checkout." && ".$checkout_now." <= ".$checkin."<br>";
					
			$overlap = Helper::checkOverlap($checkout_is,$checkin_is,$checkout_now,$checkin_now);
			if($overlap >0)
            //if ($checkout_now >= $checkout_is && $checkout_now <= $checkin_is)
                {
				echo "treffer<br>";
				echo "Die Zeiträume überschneiden sich um $overlap Tag(e).";
                    // return redirect()->back() ->with('alert',$checkin);
                        //return redirect()->route('requestout-asset')
					return redirect()->back()->withInput($request->input())->with('error', trans('admin/hardware/message.dateOverlap'))->with('2');  
                    }
            } 
		if ($converted_checkout_at  == null || $converted_expected_checkin == null)
        {
            return redirect()->back()->withInput($request->input())
            ->with('error', trans('admin/hardware/message.no_dates'));  
        }

        if ($converted_checkout_at  == $converted_expected_checkin)
        {
            return redirect()->back()->withInput($request->input())
            ->with('error', trans('admin/hardware/message.equal_dates'));  

        }
	
		if ($converted_checkout_at  > $converted_expected_checkin)
        {
            return redirect()->back()->withInput($request->input())
            ->with('error', trans('admin/hardware/message.dateWrong'));  

        }
		
		$requestedAsset->asset_id = $asset->id;
		if(!isset(CheckoutRequest::all()->last()->id)){
			$requestedAsset->checkout_requests_id = 1;
		}else{
			// Was macht es ??
			$requestedAsset->checkout_requests_id = CheckoutRequest::all()->last()->id;
		}
        $requestedAsset->responsible_id 	 = '1'; //Hard gecodet - auf Verantwortliche Person / Admin ändern !!
        $requestedAsset->user_id = $data['user_id'] = Auth::user()->id;
        $requestedAsset->notes = $request->input('note');    
        $requestedAsset->created_at = $data['requested_date'] = date("Y-m-d H:i:s");
		$requestedAsset->expected_checkout =  $converted_checkout_at; // Uhrzeit noch falsch !!
        $requestedAsset->expected_checkin = $converted_expected_checkin; // Uhrzeit noch falsch !!
	
		$requestedAsset->save();

        $logaction->logaction('requested');
        //$asset->request();
        //$asset->increment('requests_counter', 1);
        $settings->notify(new RequestAssetNotification($data));

        return redirect()->route('requestable-assets')->with('success')->with('success', trans('admin/hardware/message.requests.success'));
    }
	
	
public function getRequestView($assetId = null)
    {
        // Dies kann auf die andere Funktion zurückgreifen
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
		$settings->notify(new RequestAssetCancelation($data));
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
	
	// -----------------------
// GRIFU | Modification. This method aproves or disaproves the requests
public function approveRequestAsset($requestId  = null, $request = null)
{
    $requestedAsset = new RequestedAsset;
    $user = Auth::user();
    $request_state = 0;     // waiting
    $requestedAsset->find($requestId);

    
    $settings = Setting::getSettings();
    $logaction = new Actionlog();
    $logaction->item_id = $requestId;
    if ($user->location_id) {
        $logaction->location_id = $user->location_id;
    }
    $logaction->target_id = $data['user_id'] = Auth::user()->id;
    $logaction->target_type = User::class;


    if(($request == 'disaprove') || ($request == 'cancel') || ($request == 'aprove')) 
    {
        // This should be a model - in the top call use model 
        $assetRequested = DB::table('requested_assets')->where('id', $requestId)->first();
        $assetId =  DB::table('requested_assets')->where('id', $requestId)->pluck('asset_id');
    

        if (is_null($asset = Asset::find($assetId))) 
        {
             // Redirect to the asset management page with error
             return redirect()->route('hardware.index')->with('error', trans('admin/hardware/message.does_not_exist'));
        }

        // Verify if the user who is validating is in fact the responsible user 
        $isResponsible = False;
        if($assetRequested->responsible_id == $user->id) $isResponsible = True;

        
        $targetUser =  User::find( $assetRequested->user_id);

        $data['asset_id'] = $assetId;
        $data['item'] = $asset[0];
        $data['target'] =  $targetUser;
        $data['item_quantity'] = 1;
        $data['note'] = '';
        $data['check_out'] = $assetRequested->expected_checkout;
        $data['check_in'] = $assetRequested->expected_checkin;
        $data['expected_checkin'] = $assetRequested->expected_checkin;
        $data['requested_date'] = date("Y-m-d H:i:s");
        $data['last_checkout'] = date("Y-m-d H:i:s");
        $data['request_id'] = $requestId;
        $data['item_type'] = Asset::class;

        $logaction->item_id = $data['asset_id'];
        $logaction->created_at = date("Y-m-d H:i:s");

        
        if(($request == 'disaprove' && $isResponsible == true) || ($request == 'cancel')) {
            
            if($request == 'disaprove') {
				$targetUser->notify(new RequestAssetRejectNotification($data));
                $request_state = 2;     // disaprobed state
                $logaction->logaction('request_not_approved');
            } else if($request == 'cancel') {
				$targetUser->notify(new RequestAssetCancelationNotification($data));
                $logaction->logaction('request_canceled');
                $request_state = 3;     //  cancel state  
            }


        } else if($request == 'aprove' && $isResponsible == true) {


             // request_state = 3 is canceled, request_state = 4 is allocated
            if ($assetRequested->request_state >= 3) {
                 return redirect()->route('hardware.index')->with('error', trans('admin/hardware/message.canceled')); 
             }

            // GRIFU - send notification
            $targetUser->notify(new RequestAssetApprovalNotification($data));

            $logaction->logaction('request_approved');
            $request_state = 1;     // Aproved request
        
        } else
        {
            // it's not the responsible user
            return redirect()->back()->with('error')->with('error', trans('admin/hardware/message.requests.error'));
        }
    

    $requestedAsset->where('id',$requestId)->update(array('request_state' => $request_state));
	
	if($request_state == 2){
		return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.rejected'));
	}elseif($request_state == 3){
		return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.admin_canceled'));
	}elseif($request_state == 1){
		return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.approved'));
	}else{
		return redirect()->back()->with('success')->with('success', trans('admin/hardware/message.requests.approved'));
		}
}
}
	
}
