const { defineStore } = window.pinia

export const useCustomerStore = defineStore('customers', {
  state: () => ({
    customers: 'okay',
  }),

  actions: {
    resetCustomers() {
      this.customers = 'okay'
    },
  }
})
