@extends('layouts/default')

{{-- Page title --}}
@section('title')
    {{ trans('admin/hardware/general.request') }}
    @parent
@stop

{{-- Page content --}}
@section('content')
<style>
	
.timepicker-picker > td.separator{
		width: inherit;
	}
.bootstrap-datetimepicker-widget .separator::after, .separator::before{
		border: none;
	}
.bootstrap-datetimepicker-widget table td{
		width: inherit;
	}
.bootstrap-datetimepicker-widget td.active {
    background-color: #337ab7;
    color: #ffffff;
    text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.25);
}
.bootstrap-datetimepicker-widget table td.disabled, .bootstrap-datetimepicker-widget table td.disabled:hover {
            background: rgba(0, 0, 0, 0) !important;
            color: #eeeeee;
            cursor: not-allowed;
}
.bootstrap-datetimepicker-widget > div.row{
		margin-left: 0px;
		margin-right: 0px;
}
		
.input-group {
            padding-left: 0px !important;
        }
    </style>
<div class="row"> 
  <!-- left column -->
  <div class="col-md-7">
    <div class="box box-default">
      <form class="form-horizontal" method="post" action="{{ route('account/requestout-asset', ['assetId' => $asset->id])}}" autocomplete="off">
        <div class="box-header with-border">
          <h2 class="box-title"> {{ trans('admin/hardware/form.tag') }} {{ $asset->asset_tag }}</h2>
        </div>
        <div class="box-body"> {{csrf_field()}}
          @if ($asset->company && $asset->company->name)
          <div class="form-group"> {{ Form::label('model', trans('general.company'), array('class' => 'col-md-3 control-label')) }}
            <div class="col-md-8">
              <p class="form-control-static"> {{ $asset->company->name }} </p>
            </div>
          </div>
          @endif 
          <!-- AssetModel name -->
          <div class="form-group"> {{ Form::label('model', trans('admin/hardware/form.model'), array('class' => 'col-md-3 control-label')) }}
            <div class="col-md-8">
              <p class="form-control-static"> @if (($asset->model) && ($asset->model->name))
                {{ $asset->model->name }}
                @else <span class="text-danger text-bold"> <i class="fas fa-exclamation-triangle"></i>{{ trans('admin/hardware/general.model_invalid')}} <a href="{{ route('hardware.edit', $asset->id) }}"></a> {{ trans('admin/hardware/general.model_invalid_fix')}}</span> @endif </p>
            </div>
          </div>
          
          <!-- Asset Name -->
          <div class="form-group {{ $errors->has('name') ? 'error' : '' }}"> {{ Form::label('name', trans('admin/hardware/form.name'), array('class' => 'col-md-3 control-label')) }}
            <div class="col-md-8">
              <input class="form-control" type="text" name="name" id="name" value="{{ old('name', $asset->name) }}" tabindex="1">
              {!! $errors->first('name', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!} </div>
          </div>
          <input type="hidden" name="status_id" value="2">
          @include ('partials.forms.checkout-selector', ['user_select' => 'true','asset_select' => 'false', 'location_select' => 'false'])
          
          @include ('partials.forms.edit.user-select', ['translated_name' => trans('general.user'), 'user_id' => $user->id, 'fieldname' => 'assigned_user', 'required'=>'false']) 
          
          <!-- We have to pass unselect here so that we don't default to the asset that's being checked out. We want that asset to be pre-selected everywhere else. --> 
          @include ('partials.forms.edit.asset-select', ['translated_name' => trans('general.asset'), 'fieldname' => 'assigned_asset', 'unselect' => 'true', 'style' => 'display:none;', 'required'=>'true'])
          
          @include ('partials.forms.edit.location-select', ['translated_name' => trans('general.location'), 'fieldname' => 'assigned_location', 'style' => 'display:none;', 'required'=>'true']) 
          
          <!-- Checkout/Checkin Date -->
          <div class="form-group {{ $errors->has('checkout_at') ? 'error' : '' }}"> {{ Form::label('checkout_at', trans('admin/hardware/form.checkout_date'), array('class' => 'col-md-3 control-label')) }}
            <div class="col-md-8">
              <div class="input-group date col-md-7" data-provide="datetimepicker" data-date-format="Y-m-d H:i">
                <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="checkout_at" id="checkout_at" value="{{ old('checkout_at', date('d-m-Y H:i')) }}" required>
                <span class="input-group-addon"><i class="fas fa-calendar" aria-hidden="true"></i></span> </div>
              {!! $errors->first('checkout_at', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!} </div>
          </div>
          
          <!-- Expected Checkin Date -->
          <div class="form-group {{ $errors->has('expected_checkin') ? 'error' : '' }}"> {{ Form::label('expected_checkin', trans('admin/hardware/form.expected_checkin'), array('class' => 'col-md-3 control-label')) }}
            <div class="col-md-8">
              <div class="input-group date col-md-7" data-provide="datetimepicker" data-date-format="Y-m-d H:i">
                <input type="text" class="form-control" placeholder="{{ trans('general.select_date') }}" name="expected_checkin" id="expected_checkin" value="{{ old('expected_checkin', date('Y-m-d H:i')) }}" required>
                <span class="input-group-addon"><i class="fas fa-calendar" aria-hidden="true"></i></span> </div>
              {!! $errors->first('expected_checkin', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!} </div>
          </div>
          
          <!-- Note -->
          <div class="form-group {{ $errors->has('note') ? 'error' : '' }}"> {{ Form::label('note', trans('admin/hardware/form.notes'), array('class' => 'col-md-3 control-label')) }}
            <div class="col-md-8">
              <textarea class="col-md-6 form-control" id="note" name="note" required>{{ old('note', $asset->note) }}</textarea>
              {!! $errors->first('note', '<span class="alert-msg" aria-hidden="true"><i class="fas fa-times" aria-hidden="true"></i> :message</span>') !!} </div>
          </div>
          @if ($asset->requireAcceptance() || $asset->getEula() || ($snipeSettings->webhook_endpoint!=''))
          <div class="form-group notification-callout">
            <div class="col-md-8 col-md-offset-3">
              <div class="callout callout-info"> @if ($asset->requireAcceptance()) <i class="far fa-envelope" aria-hidden="true"></i> {{ trans('admin/categories/general.required_acceptance') }} <br>
                @endif
                
                @if ($asset->getEula()) <i class="far fa-envelope" aria-hidden="true"></i> {{ trans('admin/categories/general.required_eula') }} <br>
                @endif
                
                @if ($snipeSettings->webhook_endpoint!='') <i class="fab fa-slack" aria-hidden="true"></i> {{ trans('general.webhook_msg_note') }}
                @endif </div>
            </div>
          </div>
          @endif </div>
        <!--/.box-body-->
        <div class="box-footer"> <a class="btn btn-link" href="{{ URL::previous() }}"> {{ trans('button.cancel') }}</a>
          <button id="register" type="submit" class="btn btn-primary pull-right"><i class="fas fa-check icon-white" aria-hidden="true"></i> {{ trans('general.user_requests_count') }}</button>
        </div>
      </form>
    </div>
  </div>
  <!--/.col-md-7--> 
  
  <!-- right column -->
  <div class="col-md-5" id="current_assets_box" style="display:none;">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h2 class="box-title">{{ trans('admin/users/general.asset_reservation') }}</h2>
      </div>
      <div class="box-body">
        <div id="current_assets_content"> 
		  <p id="datas"></p></div>
      </div>
    </div>
  </div>
</div>
@stop

@section('moar_scripts')
    @include('partials/assets-assigned') 
<script>
        //        $('#checkout_at').datepicker({
        //            clearBtn: true,
        //            todayHighlight: true,
        //            endDate: '0d',
        //            format: 'yyyy-mm-dd'
        //        });
		
		$.fn.datetimepicker.defaults.icons = {
            time: 'fas fa-clock',
            date: 'fas fa-calendar',
            up: 'fas fa-chevron-up',
            down: 'fas fa-chevron-down',
            previous: 'fas fa-chevron-left',
            next: 'fas fa-chevron-right',
            today: 'fas fa-dot-circle-o',
            clear: 'fas fa-trash',
            close: 'fas fa-times'
        };
		
		var reserved_checkout = []; // vector with reservation checkout dates
        var reserved_checkin = [];  // vector with reservation checkin dates
        var inBetweenDays = [];
	
		// Define disable days and convert dates to IsoDates for the calendar
        var ISOdate = [];   
        var dates = [];

        var curDate = new Date();   // todays day, which can be increased if this day is not avaiable
	
		// GRIFU | Modification. This should be defined dynamically, in a preferences page. 
        const checkOutHours = [9,14,18]; // Define which hours we can checkout
        const checkInHours = [8,13,17,23];  // Define which hours we can checkin
        // this constraint presents issues and should be solved in the future.
        // if we choose a specific hour for checkout in a future day and this hour is not avaiable for today, then, today becomes disable and does not allows us to choose a different hour!!!!

        var enableCheckOutHours = checkOutHours;
	
		// import dates from controller - This is used for the reservations only
        var array = JSON.parse('{!! json_encode($store) !!}');
		array.forEach(function(item){
                var startDate = moment(item['expected_checkout']);
                var stopDate = moment(item['expected_checkin']);
            
                reserved_checkout.push(startDate);
                reserved_checkin.push(stopDate);
                
                // gather the two arrays into multidimensional array for showing in the interface
                dates.push([moment(startDate), moment(stopDate)]);                              

                if (moment(stopDate).format('YYYY-MM-DD') >= moment(startDate).format('YYYY-MM-DD')){
                                        
                    // Check if the reservation is longer than one day
                    // disable dates because they are entire days in between the reservation dates
                    
                    numDays = Math.round(moment.duration(stopDate.diff(startDate)).asDays());
					
                    
                    if(numDays >= 1){
                        var reservationToday = false;                        
                        
                        // check if checkout is for today                       
                        if(moment(startDate).format('YYYY-MM-DD') == moment(curDate).format('YYYY-MM-DD')){    
                            reservationToday = true;                       
                            // check if there is avaiable hours for reservation in this day           
                            // assuming that this is the checkout date and there are more than one day of reservation, so we cannot make a reservation after the reserved hour, and          
                            if (Math.round(moment(curDate).format('HH')) > Math.round(moment(startDate).format('HH')) || Math.round(moment(startDate).format('HH')) <= checkOutHours[0]){   
                              
                                ISOdate.push(moment(startDate).format('YYYY-MM-DD'));     
                                                     
                            } 
                        }                         
                        var disableDay = 0;
						
                        
                        // check the inbetween days they should be 
                        for (var i=0; i < numDays; i++){       
                            if(i>0){                                                       
                                disableDay = moment(startDate).add(i, 'days');                                                                                     
                                inBetweenDays.push(moment(disableDay).format('YYYY-MM-DD'));    // this is a inbetween day                                                       
                                ISOdate.push(moment(disableDay).format('YYYY-MM-DD'));  // vector that olds the days to be disable
                             } else {
                                 // check if we are in the first day, if so, check if theare are hours avaiable
                                if (Math.round(moment(startDate).format('HH')) <= Math.round(checkOutHours.slice(-1)) && !reservationToday){  
                                    
                                    disableDay = moment(startDate).add(i, 'days');                           
                                     ISOdate.push(moment(disableDay).format('YYYY-MM-DD'));  // vector that olds the days to be disable
                                  }     
                             }                             

                            // Check if the next disable date is the current data and it is not today ,
                            if (moment(disableDay).format('YYYY-MM-DD') == moment(curDate).format('YYYY-MM-DD') && !reservationToday) {                             
                                defaultCheckout = moment(disableDay).add(1, 'days').format('YYYY-MM-DD');                                   
                                checkoutAvaiableDay = defaultCheckout;
                                curDate = defaultCheckout;    // make today the new avaiable day to check if is avaiable in the next loop                                    
                            }                                                                                    
                        }                       
                        // check if the last day of reservation is avaiable for other reservations
                        if (Math.round(moment(stopDate).format('HH')) >= Math.round(checkOutHours.slice(-1))){                           
                            ISOdate.push(moment(stopDate).format('YYYY-MM-DD'));
                            
                        }                     



                        // if the date is today, and if the hour is less than the first checkout, let's move ahead
                        if(moment(curDate).format('YYYY-MM-DD') == moment(startDate).format('YYYY-MM-DD')){
                            // let's check if this is a multi-day reservation                            
                            if (moment(stopDate).format('YYYY-MM-DD') > moment(startDate).format('YYYY-MM-DD')){
                                // check if today's hour is above the checkout hour, if so, we must move to the date of the checkin
                               var currentHour = Math.round(moment(curDate).format('HH'));
                               // check the avaiable hours for this day before the checkout
                                var checkoutAvaiableHourBefore = checkOutHours.filter(function(x) {
                                     return x < moment(startDate).format('HH');
                                 });

                               // returns the checkout hour before the checkout of the reservation 
                                var previousHoursCheckout = checkoutAvaiableHourBefore.filter(function (hourCheck){
                                    return hourCheck <= moment(startDate).format('HH');
                                });
                                var previousHour = Math.max.apply(null,previousHoursCheckout );                                

                                // if the current hour is after the checkout or if the current hour is greater than the avaiable checkout hour 
                                if (currentHour > previousHour){
                                    // we have to jump to the stopDate and increase the hour on checkout to be after the checkin                                    
                                    defaultCheckout = moment(stopDate).format('YYYY-MM-DD');
                                    checkoutAvaiableDay = defaultCheckout;                               
                                }
                            }
                        }

                 

                           
                        
                    } else {
                                                
                        //Math.round(moment.duration(stopDate.diff(startDate)).asDays());
                        // check if the day is avaiable for other reservations or if the reservation is for all day
                        if ((Math.round(moment(startDate).format('HH')) < Math.round(checkOutHours[1])) && (Math.round(moment(stopDate).format('HH')) >= Math.round(checkOutHours.slice(-1)))){
                            ISOdate.push(moment(startDate).format('YYYY-MM-DD'));
                            if (moment(startDate).format('YYYY-MM-DD') == moment(curDate).format('YYYY-MM-DD')) { 
                                defaultCheckout = moment(startDate).add(1, 'days').format('YYYY-MM-DD');
                                
                                checkoutAvaiableDay = defaultCheckout;
                                curDate = checkoutAvaiableDay; // make today the new avaiable day to check if is avaiable in the next loop
                                
                            } 
                        } 
                    }                    
                }                
            });
	
		console.log(dates);
	
		// shows reservation dates in the interface in order
        dates.sort(function (element_a, element_b) {
                return element_a[0] - element_b[0];
            });
            
        dates.forEach(function(item,index){
                document.getElementById("datas").innerHTML = document.getElementById("datas").innerHTML+" <br> "+"{{ trans('admin/users/general.from') }} "+moment(item[0]).format('DD.MM.YYYY HH:mm')+" Uhr -  {{ trans('admin/users/general.till') }} "+moment(item[1]).format('DD.MM.YYYY HH:mm')+" Uhr";
            });
		
		//var $start = $('#checkout_at');
		//$(document).ready( update_assigned_assets_box({{ $user->id }}));
		$('#current_assets_box').fadeIn();
	
	
		
		$('#checkout_at').datetimepicker({
          	locale: 'de', // Extract this from the language selection
            minDate: new Date(),  // today date
			sideBySide: true,
         //   daysOfWeekDisabled: [0, 6],  // this should be set in the configuration 
            format: 'D.M.Y HH:mm'
        });
        $('#expected_checkin').datetimepicker({
			useCurrent: false, //Important! See issue #1075
          	locale: 'de', // Extract this from the language selection
			sideBySide: true,
         //   daysOfWeekDisabled: [0, 6],  // this should be set in the configuration 
            format: 'D.M.Y HH:mm'
        });
		
		$("#checkout_at").on("dp.change", function (e) {
           $('#expected_checkin').data("DateTimePicker").minDate(e.date);
       });
       $("#expected_checkin").on("dp.change", function (e) {
           $('#checkout_at').data("DateTimePicker").maxDate(e.date);
       });


    </script> 
@stop 