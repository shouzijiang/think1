import { API_BASE_URL } from '../config'

let socketTask = null
let isConnected = false
let connectCallbacks = []
let messageCallbacks = {}

export const wsApi = {
  connect(token) {
    if (isConnected) return Promise.resolve()
    
    return new Promise((resolve, reject) => {
      // 替换 http 为 ws
      const wsUrl = API_BASE_URL.replace(/^http/, 'ws').replace('/api', '') + ':2345'
      
      socketTask = uni.connectSocket({
        url: wsUrl,
        success: () => console.log('WebSocket 连接中...'),
        fail: (err) => reject(err)
      })

      socketTask.onOpen(() => {
        isConnected = true
        // 鉴权
        this.send({
          action: 'auth',
          token: token
        })
      })

      socketTask.onMessage((res) => {
        try {
          const data = JSON.parse(res.data)
          console.log('WS Receive:', data)
          
          if (data.action === 'auth_success') {
            resolve(data)
            connectCallbacks.forEach(cb => cb())
            connectCallbacks = []
          } else if (data.action === 'error' && data.msg === 'Need auth first') {
            reject(new Error('Auth failed'))
          }
          
          if (data.action && messageCallbacks[data.action]) {
            messageCallbacks[data.action].forEach(cb => cb(data))
          }
        } catch (e) {
          console.error('WS Parse Error:', e)
        }
      })

      socketTask.onClose(() => {
        isConnected = false
        console.log('WebSocket 已断开')
      })

      socketTask.onError((err) => {
        isConnected = false
        console.error('WebSocket 错误:', err)
        reject(err)
      })
    })
  },

  send(data) {
    if (!isConnected) {
      console.warn('WebSocket 未连接，无法发送数据')
      return
    }
    console.log('WS Send:', data)
    socketTask.send({
      data: JSON.stringify(data)
    })
  },

  on(action, callback) {
    if (!messageCallbacks[action]) {
      messageCallbacks[action] = []
    }
    messageCallbacks[action].push(callback)
  },

  off(action, callback) {
    if (!messageCallbacks[action]) return
    if (callback) {
      messageCallbacks[action] = messageCallbacks[action].filter(cb => cb !== callback)
    } else {
      messageCallbacks[action] = []
    }
  },

  close() {
    if (socketTask) {
      socketTask.close()
      socketTask = null
    }
    isConnected = false
    messageCallbacks = {}
  }
}