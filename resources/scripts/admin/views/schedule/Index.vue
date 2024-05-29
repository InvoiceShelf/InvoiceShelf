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
                <input type="text" class="form-control" id="title" name="title">
                <span id="titleError" class="text-danger"></span>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <input type="text" class="form-control" id="description" name="description">
              </div>

              <div class="mb-3">
                <label class="form-label">Installer</label>
                <select class="form-select" id="installer" name="installer">
                  
                </select>                
              </div>

              <div class="row">
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">Start Date/Time</label>
                    <input type="datetime-local" class="form-control" id="start" name="start">
                    <span id="startError" class="text-danger"></span>
                  </div>
                </div>
                <div class="col-6">
                  <div class="mb-3">
                    <label class="form-label">End Date/Time</label>
                    <input type="datetime-local" class="form-control" id="end" name="end">
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

  //Installer Select Initializing
  $.ajax({
      url:"/get-installers",
      type:"GET",
      dataType:'json',
      success:function(response)
      {
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
      //eventColor: 'red',
      events: '/schedules',
      //events: '/get-schedules?user_id='+userStore.currentUser.id+'&company_id='+userStore.currentUser.companies[0].id,
      editable: true,
      selectable: true,
    
      // eventClick: function(info) {
      //     // Get the event data
      //     var event = info.event;
      //     alert('Event: ' + event.title + '\nStart: ' + event.start + '\nEnd: ' + event.end + '\nID: ' + event.id);
      // },

      dateClick: function(info)
      {
        $('#title').val('');
        $('#description').val('');
        $('#start').val(moment(info.dateStr).format('YYYY-MM-DDTHH:mm'));
        
        $('#schedule_modal').modal('toggle');

        $('#saveBtn').click(function()
        {
          var title = $('#title').val();
          var description = $('#description').val();          
          var start = $('#start').val();
          var end = $('#end').val();
          var installer_id = $('#installer').val();

          $.ajax({
            url:"/schedules",
            type:"POST",
            dataType:'json',
            data:{ title, start, end, description, installer_id },
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
                }
            },
          });

        });
 
      },

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

.fc .fc-view {
  background-color: #fff !important;
  background: #fff !important;
}

/* .fc-toolbar {
  background-color: #e2e8f0;
} */

/* .fc-day, .fc-daygrid-day {
  background-color: #ffffff;
} */

.fc > .fc-event {
  background-color: #337ab7 !important;
  border-color: #2e6da4 !important;
  color: #ffffff !important;
} 
</style>


<!-- <template>
  <BasePage>
    <BasePageHeader :title="$t('items.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem :title="$t('items.item', 2)" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <div class="flex items-center justify-end space-x-5">
          <BaseButton
            v-show="itemStore.totalItems"
            variant="primary-outline"
            @click="toggleFilter"
          >
            {{ $t('general.filter') }}
            <template #right="slotProps">
              <BaseIcon
                v-if="!showFilters"
                :class="slotProps.class"
                name="FilterIcon"
              />
              <BaseIcon v-else name="XIcon" :class="slotProps.class" />
            </template>
          </BaseButton>

          <BaseButton
            v-if="userStore.hasAbilities(abilities.CREATE_ITEM)"
            @click="$router.push('/admin/items/create')"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            {{ $t('items.add_item') }}
          </BaseButton>
        </div>
      </template>
    </BasePageHeader>

    <BaseFilterWrapper :show="showFilters" class="mt-5" @clear="clearFilter">
      <BaseInputGroup :label="$t('items.name')" class="text-left">
        <BaseInput
          v-model="filters.name"
          type="text"
          name="name"
          autocomplete="off"
        />
      </BaseInputGroup>

      <BaseInputGroup :label="$t('items.unit')" class="text-left">
        <BaseMultiselect
          v-model="filters.unit_id"
          :placeholder="$t('items.select_a_unit')"
          value-prop="id"
          track-by="name"
          :filter-results="false"
          label="name"
          resolve-on-load
          :delay="500"
          searchable
          class="w-full"
          :options="searchUnits"
        />
      </BaseInputGroup>

      <BaseInputGroup class="text-left" :label="$t('items.price')">
        <BaseMoney v-model="filters.price" />
      </BaseInputGroup>
    </BaseFilterWrapper>

    <BaseEmptyPlaceholder
      v-show="showEmptyScreen"
      :title="$t('items.no_items')"
      :description="$t('items.list_of_items')"
    >
      <SatelliteIcon class="mt-5 mb-4" />

      <template #actions>
        <BaseButton
          v-if="userStore.hasAbilities(abilities.CREATE_ITEM)"
          variant="primary-outline"
          @click="$router.push('/admin/items/create')"
        >
          <template #left="slotProps">
            <BaseIcon name="PlusIcon" :class="slotProps.class" />
          </template>
          {{ $t('items.add_new_item') }}
        </BaseButton>
      </template>
    </BaseEmptyPlaceholder>

    <div v-show="!showEmptyScreen" class="relative table-container">
      <div
        class="
          relative
          flex
          items-center
          justify-end
          h-5
          border-gray-200 border-solid
        "
      >
        <BaseDropdown v-if="itemStore.selectedItems.length">
          <template #activator>
            <span
              class="
                flex
                text-sm
                font-medium
                cursor-pointer
                select-none
                text-primary-400
              "
            >
              {{ $t('general.actions') }}
              <BaseIcon name="ChevronDownIcon" />
            </span>
          </template>
          <BaseDropdownItem @click="removeMultipleItems">
            <BaseIcon name="TrashIcon" class="mr-3 text-gray-600" />
            {{ $t('general.delete') }}
          </BaseDropdownItem>
        </BaseDropdown>
      </div>

      <BaseTable
        ref="table"
        :data="fetchData"
        :columns="itemColumns"
        :placeholder-count="itemStore.totalItems >= 20 ? 10 : 5"
        class="mt-3"
      >
        <template #header>
          <div class="absolute items-center left-6 top-2.5 select-none">
            <BaseCheckbox
              v-model="itemStore.selectAllField"
              variant="primary"
              @change="itemStore.selectAllItems"
            />
          </div>
        </template>

        <template #cell-status="{ row }">
          <div class="relative block">
            <BaseCheckbox
              :id="row.id"
              v-model="selectField"
              :value="row.data.id"
            />
          </div>
        </template>

        <template #cell-name="{ row }">
          <router-link
            :to="{ path: `items/${row.data.id}/edit` }"
            class="font-medium text-primary-500"
          >
            {{ row.data.name }}
          </router-link>
        </template>

        <template #cell-unit_name="{ row }">
          <span>
            {{ row.data.unit ? row.data.unit.name : '-' }}
          </span>
        </template>

        <template #cell-price="{ row }">
          <BaseFormatMoney
            :amount="row.data.price"
            :currency="companyStore.selectedCompanyCurrency"
          />
        </template>

        <template #cell-created_at="{ row }">
          <span>{{ row.data.formatted_created_at }}</span>
        </template>

        <template v-if="hasAbilities()" #cell-actions="{ row }">
          <ItemDropdown
            :row="row.data"
            :table="table"
            :load-data="refreshTable"
          />
        </template>
      </BaseTable>
    </div>
  </BasePage>
</template>

<script setup>
import { ref, computed, inject, onMounted, reactive, onUnmounted } from 'vue'
import { debouncedWatch } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useItemStore } from '@/scripts/admin/stores/item'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useUserStore } from '@/scripts/admin/stores/user'
import ItemDropdown from '@/scripts/admin/components/dropdowns/ItemIndexDropdown.vue'
import SatelliteIcon from '@/scripts/components/icons/empty/SatelliteIcon.vue'
import abilities from '@/scripts/admin/stub/abilities'

const utils = inject('utils')

const itemStore = useItemStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const { t } = useI18n()
let showFilters = ref(false)
let isFetchingInitialData = ref(true)

const filters = reactive({
  name: '',
  unit_id: '',
  price: '',
})

const table = ref(null)

const showEmptyScreen = computed(
  () => !itemStore.totalItems && !isFetchingInitialData.value
)

const selectField = computed({
  get: () => itemStore.selectedItems,
  set: (value) => {
    return itemStore.selectItem(value)
  },
})

const itemColumns = computed(() => {
  return [
    {
      key: 'status',
      thClass: 'extra w-10',
      tdClass: 'font-medium text-gray-900',
      placeholderClass: 'w-10',
      sortable: false,
    },
    {
      key: 'name',
      label: t('items.name'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    { key: 'unit_name', label: t('items.unit') },
    { key: 'price', label: t('items.price') },
    { key: 'created_at', label: t('items.added_on') },

    {
      key: 'actions',
      thClass: 'text-right',
      tdClass: 'text-right text-sm font-medium',
      sortable: false,
    },
  ]
})

debouncedWatch(
  filters,
  () => {
    setFilters()
  },
  { debounce: 500 }
)

itemStore.fetchItemUnits({ limit: 'all' })

onUnmounted(() => {
  if (itemStore.selectAllField) {
    itemStore.selectAllItems()
  }
})

function clearFilter() {
  filters.name = ''
  filters.unit_id = ''
  filters.price = ''
}

function hasAbilities() {
  return userStore.hasAbilities([abilities.DELETE_ITEM, abilities.EDIT_ITEM])
}

function toggleFilter() {
  if (showFilters.value) {
    clearFilter()
  }

  showFilters.value = !showFilters.value
}

function refreshTable() {
  table.value && table.value.refresh()
}

function setFilters() {
  refreshTable()
}

async function searchUnits(search) {
  let res = await itemStore.fetchItemUnits({ search })

  return res.data.data
}

async function fetchData({ page, filter, sort }) {
  let data = {
    search: filters.name,
    unit_id: filters.unit_id !== null ? filters.unit_id : '',
    price: Math.round(filters.price * 100),
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
  }

  isFetchingInitialData.value = true

  let response = await itemStore.fetchItems(data)

  isFetchingInitialData.value = false

  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta.last_page,
      currentPage: page,
      totalCount: response.data.meta.total,
      limit: 10,
    },
  }
}

function removeMultipleItems() {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('items.confirm_delete', 2),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      hideNoButton: false,
      size: 'lg',
    })
    .then((res) => {
      if (res) {
        itemStore.deleteMultipleItems().then((response) => {
          if (response.data.success) {
            table.value && table.value.refresh()
          }
        })
      }
    })
}
</script> -->
