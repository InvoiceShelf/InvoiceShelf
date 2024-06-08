<template>
  <BasePage>
    <BasePageHeader :title="$t('schedules.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('schedules.schedule', 2)"
          to="#"
          active
        />
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
    <div id="calendar"></div>
    <!-- <FullCalendar :options="calendarOptions" /> -->
  </BasePage>

  <!-- MODAL -->
  <div class="namespace_bootstrap">
    <div class="modal" id="schedule_modal" tabindex="-1" data-bs-theme="light">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-light">
          <div class="modal-header">
            <h1 class="modal-title fs-5" id="exampleModalLabel">
              Add Schedule
            </h1>
            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>

          <div class="modal-body">
            <form>
              <div class="mb-3">
                <label class="form-label">Title</label>
                <input
                  type="text"
                  class="form-control field"
                  id="title"
                  name="title"
                />
                <span id="titleError" class="text-danger error"></span>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <input
                  type="text"
                  class="form-control field"
                  id="description"
                  name="description"
                />
              </div>

              <div class="row">
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">Customer</label>
                    <select
                      class="form-select field"
                      id="customer"
                      name="customer"
                    ></select>
                    <span id="customerError" class="text-danger error"></span>
                  </div>
                </div>
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">Installer</label>
                    <select
                      class="form-select field"
                      id="installer"
                      name="installer"
                    ></select>
                    <span id="installerError" class="text-danger error"></span>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">Start Date/Time</label>
                    <input
                      type="datetime-local"
                      class="form-control"
                      id="start"
                      name="start"
                    />
                    <span id="startError" class="text-danger error"></span>
                  </div>
                </div>
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">End Date/Time</label>
                    <input
                      type="datetime-local"
                      class="form-control field"
                      id="end"
                      name="end"
                    />
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              Close
            </button>
            <button type="button" id="saveBtn" class="btn btn-primary">
              {{ buttonText }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<!-- FULL CALENDAR-->
<script setup>
import { Calendar } from '@fullcalendar/core'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'

import moment from 'moment'
import 'bootstrap'

import { ref, computed } from 'vue'
import { onMounted } from 'vue'
//import { useUserStore } from '@/scripts/admin/stores/user'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const notificationStore = useNotificationStore()

//To verify se button is update or save
let id = ref(null)
const buttonText = computed(() => {
  return id.value ? 'Update' : 'Save'
})

//const userStore = useUserStore()
// console.log(userStore.currentUser);
// console.log(userStore.currentUser.id);

$.ajaxSetup({
  headers: {
    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
  },
})

//Customer Select Initializing
$.ajax({
  url: '/get-customers',
  type: 'GET',
  dataType: 'json',
  success: function (response) {
    $('#customer').empty()
    $('#customer').append('<option value=""></option>')
    response.forEach(function (customer) {
      $('#customer').append(
        '<option value="' + customer.id + '">' + customer.name + '</option>',
      )
    })
  },
  error: function (error) {
    alert('Something went wrong to get the customers list: ' + error)
  },
})

//Installer Select Initializing
$.ajax({
  url: '/get-installers',
  type: 'GET',
  dataType: 'json',
  success: function (response) {
    $('#installer').empty()
    $('#installer').append('<option value=""></option>')
    response.forEach(function (installer) {
      $('#installer').append(
        '<option value="' + installer.id + '">' + installer.name + '</option>',
      )
    })
  },
  error: function (error) {
    alert('Something went wrong to get the installers list: ' + error)
  },
})

onMounted(() => {
  var calendarEl = document.getElementById('calendar')

  var calendar = new Calendar(calendarEl, {
    plugins: [dayGridPlugin, interactionPlugin, timeGridPlugin, listPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
    },
    events: '/schedules',
    eventBackgroundColor: '#ddd',
    eventBorderColor: '#ccc',
    eventTextColor: '#000',
    eventDisplay: 'block',
    editable: true,
    selectable: true,

    //CREATE SCHEDULE
    dateClick: function (info) {
      $('.field').val('')
      $('.error').empty()
      //reset id Ref
      id.value = null

      $('#start').val(moment(info.dateStr).format('YYYY-MM-DDTHH:mm'))
      $('#schedule_modal').modal('toggle')
      //reassign saveBtn click event
      saveBtn()
    },

    //CLICK ON THE SCHEDULE
    eventClick: function (info) {
      // Get the event data
      var event = info.event

      $('#title').val(event.title)
      $('#start').val(moment(event.start).format('YYYY-MM-DDTHH:mm'))
      $('#end').val(moment(event.end).format('YYYY-MM-DDTHH:mm'))
      $('#description').val(event.extendedProps.description)
      $('#customer').val(event.extendedProps.customer_id)
      $('#installer').val(event.extendedProps.installer_id)

      $('#schedule_modal').modal('toggle')

      if (event.id) {
        id.value = event.id
        //reassign saveBtn click event
        saveBtn()
      }
    },
  })

  //SAVE OR UPDATE SCHEDULE
  function saveBtn() {
    $('#saveBtn').click(function () {
      var title = $('#title').val()
      var description = $('#description').val()
      var start = $('#start').val()
      var end = $('#end').val()
      var customer_id = $('#customer').val()
      var installer_id = $('#installer').val()

      //UPDATE
      if (id.value) {
        $.ajax({
          url: '/schedules/' + id.value,
          type: 'PUT',
          dataType: 'json',
          data: { title, start, end, description, customer_id, installer_id },
          success: function (response) {
            $('#schedule_modal').modal('toggle')

            var event = calendar.getEventById(id.value)
            event.setProp('title', response.title)
            event.setStart(response.start)
            event.setEnd(response.end)
            event.setExtendedProp('description', response.description)
            event.setExtendedProp('customer_id', response.customer_id)
            event.setExtendedProp('installer_id', response.installer_id)

            notificationStore.showNotification({
              type: 'success',
              message: t('schedules.updated_message'),
            })
            //reset id Ref
            id.value = null
          },
          error: function (error) {
            if (error) {
              $('#titleError').html(error.responseJSON.errors.title)
              $('#startError').html(error.responseJSON.errors.start)
              $('#customerError').html(error.responseJSON.errors.customer_id)
              $('#installerError').html(error.responseJSON.errors.installer_id)
            }
          },
        })
      }
      //SAVE
      else {
        $.ajax({
          url: '/schedules',
          type: 'POST',
          dataType: 'json',
          data: { title, start, end, description, customer_id, installer_id },
          success: function (response) {
            $('#schedule_modal').modal('toggle')
            var newEvent = response
            calendar.addEvent(newEvent)
            notificationStore.showNotification({
              type: 'success',
              message: t('schedules.created_message'),
            })
          },
          error: function (error) {
            if (error) {
              $('#titleError').html(error.responseJSON.errors.title)
              $('#startError').html(error.responseJSON.errors.start)
              $('#customerError').html(error.responseJSON.errors.customer_id)
              $('#installerError').html(error.responseJSON.errors.installer_id)
            }
          },
        })
      }
    })
  }

  $('#schedule_modal').on('hidden.bs.modal', function () {
    $('#saveBtn').off()
  })

  $('#add_schedule').click(function () {
    $('.field').val('')
    $('.error').empty()
    $('#schedule_modal').modal('toggle')
    //reassign saveBtn click event
    saveBtn()
  })

  calendar.render()
})
</script>

<style scoped></style>
