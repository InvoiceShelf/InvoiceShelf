<template>
  <BasePage>
    <BasePageHeader :title="$t('schedules.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('schedules.schedule', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton id="add_schedule">
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('schedules.add_schedule') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>
    <!-- CALENDAR-->
    <div id='calendar'></div>
  </BasePage>



  <!-- MODAL -->
  <div class="modal fade" id="schedule_modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

          <div class="modal-header">
            <h1 class="modal-title fs-5" id="exampleModalLabel">Add Schedule</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <form>
              <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" class="form-control field" id="title" name="title">
                <span id="titleError" class="text-danger error"></span>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <input type="text" class="form-control field" id="description" name="description">
              </div>

              <div class="mb-3">
                <label class="form-label">Customer</label>
                <select class="form-select field" id="customer" name="customer">
                  
                </select>
                <span id="customerError" class="text-danger error"></span>            
              </div>

              <div class="mb-3">
                <label class="form-label">Installer</label>
                <select class="form-select field" id="installer" name="installer">
                  
                </select>
                <span id="installerError" class="text-danger error"></span>               
              </div>

              <div class="row">
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">Start Date/Time</label>
                    <input type="datetime-local" class="form-control" id="start" name="start">
                    <span id="startError" class="text-danger error"></span>
                  </div>
                </div>
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">End Date/Time</label>
                    <input type="datetime-local" class="form-control field" id="end" name="end">
                  </div>
                </div>
              </div>

            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" id="saveBtn" class="btn btn-primary">Save changes</button>
          </div>

        </div>
      </div>  
  </div>
  

</template>

<!-- FULL CALENDAR-->
<script setup>
import { onMounted } from 'vue'
import { useUserStore } from '@/scripts/admin/stores/user'

const userStore = useUserStore()
// console.log(userStore.currentUser);
// console.log(userStore.currentUser.id);

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });


  //Customer Select Initializing
  $.ajax({
      url:"/get-customers",
      type:"GET",
      dataType:'json',
      success:function(response)
      {        
        $('#customer').empty();
        $('#customer').append('<option value=""></option>');
        response.forEach(function(customer) {
            $('#customer').append('<option value="' + customer.id + '">' + customer.name + '</option>');
        });
      },
      error:function(error)
      {
          alert('Something went wrong to get the customers list: '+error);
      },
  });

  //Installer Select Initializing
  $.ajax({
      url:"/get-installers",
      type:"GET",
      dataType:'json',
      success:function(response)
      {
        $('#installer').empty();
        $('#installer').append('<option value=""></option>');
        response.forEach(function(installer) {
            $('#installer').append('<option value="' + installer.id + '">' + installer.name + '</option>');
        });
      },
      error:function(error)
      {
          alert('Something went wrong to get the installers list: '+error);
      },
  });

  onMounted(() => {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      //themeSystem: 'bootstrap5',
      initialView: 'dayGridMonth',
      headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      timeZone:'UTC',
      
      events: '/schedules',
      eventBackgroundColor: '#ddd',
      eventBorderColor: '#ccc',
      eventTextColor: '#000',
      eventDisplay:'block',
      //events: '/get-schedules?user_id='+userStore.currentUser.id+'&company_id='+userStore.currentUser.companies[0].id,
      editable: true,
      selectable: true,
    
      // eventClick: function(info) {
      //     // Get the event data
      //     var event = info.event;
      //     alert('Event: ' + event.title + '\nStart: ' + event.start + '\nEnd: ' + event.end + '\nID: ' + event.id);
      // },

      //SHOW TOOLTIP
      eventDidMount: function(info) {
        var tooltip = new Tooltip(info.el, {
          title: info.event.extendedProps.description,
          placement: 'top',
          trigger: 'hover',
          container: 'body'
        });
      },

      //CREATE SCHEDULE
      dateClick: function(info)
      {
        $('.field').val('');        
        $('.error').empty();

        $('#start').val(moment(info.dateStr).format('YYYY-MM-DDTHH:mm'));        
        $('#schedule_modal').modal('toggle');

        $('#saveBtn').click(function()
        {
          var title = $('#title').val();
          var description = $('#description').val();          
          var start = $('#start').val();
          var end = $('#end').val();
          var customer_id = $('#customer').val();
          var installer_id = $('#installer').val();

          $.ajax({
            url:"/schedules",
            type:"POST",
            dataType:'json',
            data:{ title, start, end, description, customer_id, installer_id },
            success:function(response)
            {
                $('#schedule_modal').modal('hide');
                var newEvent = {
                  'id': response.id,
                  'title': response.title,
                  'start' : response.start,
                  'end'  : response.end
                }
                calendar.addEvent(newEvent);

            },
            error:function(error)
            {
                if(error) {
                    $('#titleError').html(error.responseJSON.errors.title);
                    $('#startError').html(error.responseJSON.errors.start);
                    $('#customerError').html(error.responseJSON.errors.customer_id);
                    $('#installerError').html(error.responseJSON.errors.installer_id);
                }
            },
          });

        });
 
      },

      eventMouseEnter: function(info) {
          // Extract event details
          var eventObj = info.event;

          var tooltipContent = `
          <strong>Description:</strong> ${eventObj.extendedProps.description}<br>
          <strong>Customer:</strong> ${eventObj.extendedProps.customer.name}<br>
          <strong>Installer:</strong> ${eventObj.extendedProps.installer.name}`;

          // Create tooltip
          var tooltip = new bootstrap.Tooltip(info.el, {
              title: tooltipContent,
              html: true,
              placement: 'top',
              trigger: 'manual'
          });

          // Show tooltip
          tooltip.show();

          // Store tooltip instance for later hiding
          $(info.el).data('tooltip', tooltip);

          // // Fetch additional information based on customer_id
          // $.ajax({
          //     url: '/get-customer-name', // Your API endpoint to get customer name
          //     type: 'GET',
          //     data: { customer_id: eventObj.extendedProps.customer_id },
          //     success: function(response) {
          //         var tooltipContent = `<strong>Description:</strong> ${eventObj.extendedProps.description}<br><strong>Customer:</strong> ${response.customer_name}`;

          //         // Create tooltip
          //         var tooltip = new bootstrap.Tooltip(info.el, {
          //             title: tooltipContent,
          //             html: true,
          //             placement: 'top',
          //             trigger: 'manual'
          //         });

          //         // Show tooltip
          //         tooltip.show();

          //         // Store tooltip instance for later hiding
          //         $(info.el).data('tooltip', tooltip);
          //     }
          // });
      },
      eventMouseLeave: function(info) {
          // Hide and dispose the tooltip
          var tooltip = $(info.el).data('tooltip');
          if (tooltip) {
              tooltip.dispose();
          }
      }








    });

    $('#schedule_modal').on('hidden.bs.modal',function() {      
      $('#saveBtn').unbind();
    });

    $('#add_schedule').click(function(){
      $('#schedule_modal').modal('toggle');
    })

    calendar.render();

  });

</script>

<style scoped>

@import 'bootstrap.min.css';


/* .fc .fc-view {
  background-color: #fff !important;
  background: #fff !important;
} */

/* .fc-toolbar {
  background-color: #e2e8f0;
} */

/* .fc-day, .fc-daygrid-day {
  background-color: #ffffff;
} */

/* .fc > .fc-event {
  background-color: #337ab7 !important;
  border-color: #2e6da4 !important;
  color: #ffffff !important;
}  */
</style>

