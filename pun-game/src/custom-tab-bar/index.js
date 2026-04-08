Component({
  data: {
    selected: 0,
  },
  methods: {
    switchTab(e) {
      const index = Number(e.currentTarget.dataset.index)
      const path = e.currentTarget.dataset.path
      if (!Number.isFinite(index) || !path) return
      if (this.data.selected === index) return
      wx.switchTab({ url: path })
    },
  },
  lifetimes: {
    attached() {
      const pages = getCurrentPages()
      const current = pages && pages.length ? pages[pages.length - 1] : null
      const route = current && current.route ? `/${current.route}` : ''
      const map = {
        '/pages/index/index': 0,
        '/pages/rank/rank': 1,
        '/pages/levels/levels': 2,
        '/pages/mine/mine': 3,
      }
      const selected = map[route]
      if (selected !== undefined) {
        this.setData({ selected })
      }
    },
  },
})
