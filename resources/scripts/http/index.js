import axios from 'axios'
import Ls from '@/scripts/services/ls.js'

window.Ls = Ls

const instance = axios.create({
  withCredentials: true,
  headers: {
    common: {
      'X-Requested-With': 'XMLHttpRequest',
    },
  },
})

instance.interceptors.request.use(function (config) {
  const companyId = Ls.get('selectedCompany')
  const authToken = Ls.get('auth.token')

  if (authToken) {
    config.headers.Authorization = authToken
  }

  if (companyId) {
    config.headers.company = companyId
  }

  return config
})

function http(config) {
  return instance(config)
}

http.get = instance.get.bind(instance)
http.post = instance.post.bind(instance)
http.put = instance.put.bind(instance)
http.delete = instance.delete.bind(instance)
http.patch = instance.patch.bind(instance)

export default http
