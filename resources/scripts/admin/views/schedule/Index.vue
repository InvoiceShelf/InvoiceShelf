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
          <BaseButton @click="handleOpenModalAddSchedule">
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
            <div class="row">
              <div class="col-10">
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
              </div>

              <div class="col-2">
                <div class="mb-3">
                  <label class="form-label">Color</label>
                  <input
                    type="color"
                    class="form-control form-control-color"
                    id="color"
                    name="color"
                    v-model="selectedColor"
                  />
                </div>
              </div>
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

                  <v-select
                    class="field"
                    v-model="selected_customer"
                    :options="options_customer"
                    :reduce="(label) => label.value"
                    label="label"
                    index="value"
                  ></v-select>
                  <!-- <p>Selected Value: {{ selected_customer }}</p> -->

                  <!-- <select
                    class="form-select field"
                    id="customer"
                    name="customer"
                  ></select> -->

                  <span id="customerError" class="text-danger error"></span>
                </div>
              </div>
              <div class="col-6">
                <div class="mb-3">
                  <label class="form-label">Installer</label>

                  <v-select
                    class="field"
                    v-model="selected_installer"
                    :options="options_installer"
                    :reduce="(label) => label.value"
                    label="label"
                    index="value"
                    id="installer"
                    name="installer"
                  ></v-select>
                  <!-- <p>Selected Value: {{ selected_installer }}</p> -->

                  <!-- <select
                    class="form-select field"
                    id="installer"
                    name="installer"
                  ></select> -->

                  <span id="installerError" class="text-danger error"></span>
                </div>
              </div>
            </div>

            <!-- <v-select
              class="field"
              label="label"
              v-model="myValue"
              :options="options"
              :reduce="(label) => label.value"
            ></v-select> -->

            <!-- <div>MY VALUE:{{ myValue }}</div> -->

            <div class="row">
              <div class="col-6">
                <div class="mb-3">
                  <label class="form-label">Start Date/Time</label>
                  <input
                    type="datetime-local"
                    class="form-control field"
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
          </div>
          <div class="modal-footer">
            <button
              v-if="buttonText === 'Update'"
              @click="handleDelete"
              type="button"
              class="btn btn-danger"
            >
              Delete
            </button>
            <button
              type="button"
              class="btn btn-secondary"
              data-bs-dismiss="modal"
            >
              Close
            </button>
            <button
              type="button"
              @click="handleSaveUpdate"
              class="btn btn-primary"
            >
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

import { onMounted, ref, computed } from 'vue'
//import { useUserStore } from '@/scripts/admin/stores/user'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useI18n } from 'vue-i18n'

import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const { t } = useI18n()
const notificationStore = useNotificationStore()

//EVENT and CALENDAR, COLOR REFERENCE REACTIVE
let event = ref(null)
let calendar = ref(null)
let selectedColor = ref(null)

//To verify if submit button is update or save
const buttonText = computed(() => {
  return event.value ? 'Update' : 'Save'
})

let options_customer = ref([])
let selected_customer = ref(null)

let options_installer = ref([])
let selected_installer = ref(null)

//const userStore = useUserStore()
// console.log(userStore.currentUser);
// console.log(userStore.currentUser.id);

//Customer Select Initializing
const fetchCustomerOptions = () => {
  $.ajax({
    url: '/get-customers',
    type: 'GET',
    dataType: 'json',
    success: function (response) {
      options_customer.value = response.map((customer) => ({
        value: customer.id,
        label: customer.name,
      }))
      // $('#customer').empty()
      // $('#customer').append('<option value=""></option>')
      // response.forEach(function (customer) {
      //   $('#customer').append(
      //     '<option value="' + customer.id + '">' + customer.name + '</option>',
      //   )
      // })
    },
    error: function (error) {
      alert('Something went wrong to get the customers list: ' + error)
    },
  })
}

//Installer Select Initializing
const fetchInstallerOptions = () => {
  $.ajax({
    url: '/get-installers',
    type: 'GET',
    dataType: 'json',
    success: function (response) {
      options_installer.value = response.map((installer) => ({
        value: installer.id,
        label: installer.name,
      }))
      // $('#installer').empty()
      // $('#installer').append('<option value=""></option>')
      // response.forEach(function (installer) {
      //   $('#installer').append(
      //     '<option value="' +
      //       installer.id +
      //       '">' +
      //       installer.name +
      //       '</option>',
      //   )
      // })
    },
    error: function (error) {
      alert('Something went wrong to get the installers list: ' + error)
    },
  })
}

//Get updated CSRF Token
$.ajax({
  url: '/get-token',
  type: 'GET',
  dataType: 'json',
  success: function (response) {
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': response,
      },
    })
  },
  error: function (error) {
    alert('Something went wrong to get the updated token: ' + error)
  },
})

//SAVE OR UPDATE SCHEDULE
function handleSaveUpdate() {
  var title = $('#title').val()
  var description = $('#description').val()
  var start = $('#start').val()
  var end = $('#end').val()
  var color = $('#color').val()
  var customer_id = selected_customer.value
  var installer_id = selected_installer.value
  selectedColor.value = color

  //UPDATE
  if (event.value) {
    $.ajax({
      url: '/schedules/' + event.value.id,
      type: 'PUT',
      dataType: 'json',
      data: {
        title,
        start,
        end,
        description,
        customer_id,
        installer_id,
        color,
      },
      success: function (response) {
        $('#schedule_modal').modal('toggle')

        event.value.setProp('title', response.title)
        event.value.setStart(response.start)
        event.value.setEnd(response.end)
        event.value.setExtendedProp('description', response.description)
        event.value.setExtendedProp('customer_id', response.customer_id)
        event.value.setExtendedProp('installer_id', response.installer_id)
        event.value.setProp('backgroundColor', response.color)

        notificationStore.showNotification({
          type: 'success',
          message: t('schedules.updated_message'),
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
  //SAVE
  else {
    $.ajax({
      url: '/schedules',
      type: 'POST',
      dataType: 'json',
      data: {
        title,
        start,
        end,
        description,
        customer_id,
        installer_id,
        color,
      },
      success: function (response) {
        $('#schedule_modal').modal('toggle')
        var newEvent = response
        calendar.value.addEvent(newEvent)
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
}

//DELETE SCHEDULE
function handleDelete() {
  if (event.value) {
    if (confirm('Are you sure, you want to DELETE it?')) {
      $.ajax({
        url: '/schedules/' + event.value.id,
        type: 'DELETE',
        dataType: 'json',
        success: function (response) {
          $('#schedule_modal').modal('toggle')

          event.value.remove()

          notificationStore.showNotification({
            type: 'success',
            message: t('schedules.deleted_message'),
          })
        },
        error: function () {
          notificationStore.showNotification({
            type: 'error',
            message: t('general.something_went_wrong'),
          })
        },
      })
    }
  }
}

//OPEN MODAL ADD SCHEDULE BY TOP BUTTON and CLEAN ALL FIELDS
function handleOpenModalAddSchedule() {
  $('.field').val('')
  $('.error').empty()
  //reset event Ref, color,, customer and installer
  event.value = null
  selectedColor.value = '#dddddd'
  selected_customer.value = null
  selected_installer.value = null

  $('#schedule_modal').modal('toggle')
}

onMounted(() => {
  //Fetch Customer and Installer with vue3 Ref
  fetchCustomerOptions()
  fetchInstallerOptions()

  var calendarEl = document.getElementById('calendar')

  calendar.value = new Calendar(calendarEl, {
    plugins: [dayGridPlugin, interactionPlugin, timeGridPlugin, listPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
    },
    events: '/schedules',
    eventBackgroundColor: '#ddd',
    eventTextColor: '#000',
    eventDisplay: 'block',
    selectable: true,
    editable: true,
    dayMaxEvents: true, // when too many events in a day, show the popover

    //CREATE SCHEDULE - CLICK ON ANY DATE
    dateClick: function (info) {
      $('.field').val('')
      $('.error').empty()
      //reset event Ref
      event.value = null

      selectedColor.value = '#dddddd'
      selected_customer.value = null
      selected_installer.value = null

      $('#start').val(moment(info.dateStr).format('YYYY-MM-DDTHH:mm'))
      $('#schedule_modal').modal('toggle')
    },

    //CLICK ON SPECIFIC SCHEDULE
    eventClick: function (info) {
      //update the REF reactive event
      event.value = info.event

      $('#title').val(event.value.title)
      $('#start').val(moment(event.value.start).format('YYYY-MM-DDTHH:mm'))
      $('#end').val(moment(event.value.end).format('YYYY-MM-DDTHH:mm'))
      $('#description').val(event.value.extendedProps.description)
      $('#color').val(event.value.backgroundColor)

      selectedColor.value = event.value.backgroundColor
      selected_customer.value = Number(event.value.extendedProps.customer_id)
      selected_installer.value = Number(event.value.extendedProps.installer_id)

      $('.error').empty()
      $('#schedule_modal').modal('toggle')
    },

    //DRAG AND DROP
    eventDrop: function (info) {
      var event = info.event
      var newStartDate = moment(event.start).format('YYYY-MM-DDTHH:mm')
      var newEndDate =
        moment(event.end).format('YYYY-MM-DDTHH:mm') || newStartDate

      $.ajax({
        url: '/schedules/' + event.id + '/dragdrop',
        type: 'PUT',
        dataType: 'json',
        data: { start: newStartDate, end: newEndDate },
        success: function (response) {
          notificationStore.showNotification({
            type: 'success',
            message: 'Schedule moved successfully',
          })
        },
        error: function () {
          notificationStore.showNotification({
            type: 'error',
            message: t('general.something_went_wrong'),
          })
        },
      })
    },

    //RESIZE
    // eventResize: function (info) {
    //   var event = info.event
    //   var newEndDate = moment(event.end).format('YYYY-MM-DDTHH:mm')

    //   $.ajax({
    //     url: '/schedules/' + event.id + '/resize',
    //     type: 'PUT',
    //     dataType: 'json',
    //     data: { end: newEndDate },
    //     success: function (response) {
    //       notificationStore.showNotification({
    //         type: 'success',
    //         message: 'Schedule resized successfully',
    //       })
    //     },
    //     error: function () {
    //       notificationStore.showNotification({
    //         type: 'error',
    //         message: t('general.something_went_wrong'),
    //       })
    //     },
    //   })
    // },
  })

  calendar.value.render()
})
</script>

<style>
.vs__dropdown-menu {
  padding-left: 0px !important;
}
.vs__dropdown-toggle {
  background: #fff;
}
.vs__clear {
  margin-right: 8px !important;
}
</style>
