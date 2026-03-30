import { API_BASE_URL } from '../config'

let socketTask = null
let isConnected = false
let connectCallbacks = []
let messageCallbacks = {}

export const wsApi = {
  connect(token) {
    if (isConnected) return Promise.resolve()
    if (socketTask) {
      console.warn('Socket is currently connecting, please wait.')
      return new Promise((resolve, reject) => {
        connectCallbacks.push(() => resolve(true))
      })
    }
    
    return new Promise((resolve, reject) => {
      // 替换 http 为 ws，https 为 wss
      let wsUrl = API_BASE_URL.replace(/^https/, 'wss').replace(/^http/, 'ws')
      
      // 去掉可能存在的 /api 后缀
      if (wsUrl.endsWith('/api')) {
        wsUrl = wsUrl.replace('/api', '')
      }
      
      // 注意：如果微信小程序要求 wss，通常你需要在服务器(如Nginx/宝塔)配置反向代理
      // 将 wss://sofun.online/ws 代理到本地的 2345 端口。
      // 这里为了兼容你的本地开发，如果包含了端口号说明是本地直连
      if (!wsUrl.includes(':2345') && wsUrl.includes('127.0.0.1')) {
         wsUrl = wsUrl + ':2345'
      } else if (wsUrl.includes('sofun.online')) {
         // 线上环境，如果是 wss 不能带未备案的非常规端口，建议通过 Nginx 代理，
         // 这里假设你的 Nginx 代理了 /ws 路径到 2345 端口
         wsUrl = wsUrl + '/ws'
      } else if (!wsUrl.includes(':2345')){
         wsUrl = wsUrl + ':2345'
      }
      
      console.log('Connecting to WS:', wsUrl)
      
      socketTask = uni.connectSocket({
        url: wsUrl,
        success: () => console.log('WebSocket connect API 成功调用...'),
        fail: (err) => {
          console.error('WebSocket connect API 调用失败', err)
          socketTask = null
          reject(err)
        }
      })

      socketTask.onOpen(() => {
        console.log('WebSocket 连接已打开')
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
        socketTask = null
        console.log('WebSocket 已断开')
      })

      socketTask.onError((err) => {
        isConnected = false
        socketTask = null
        console.error('WebSocket 错误:', err)
        reject(err)
      })
    })
  },

  send(data) {
    if (!isConnected || !socketTask) {
      console.warn('WebSocket 未连接，尝试排队发送或丢弃:', data)
      // 如果没有连接成功，可以在这里把消息压入队列，或者直接提示用户
      if (data.action !== 'auth') {
         uni.showToast({ title: '网络未连接，请重试', icon: 'none' })
      }
      return
    }
    console.log('WS Send:', data)
    socketTask.send({
      data: JSON.stringify(data),
      fail: (err) => {
        console.error('发送数据失败:', err)
        uni.showToast({ title: '发送失败，请重试', icon: 'none' })
      }
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
  },

  isConnected() {
    return isConnected && !!socketTask
  }
}