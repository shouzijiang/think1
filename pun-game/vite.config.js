import { mkdirSync, writeFileSync } from 'fs'
import { dirname, join } from 'path'
import { fileURLToPath } from 'url'
import { defineConfig } from 'vite'
import uni from '@dcloudio/vite-plugin-uni'

const __dirname = dirname(fileURLToPath(import.meta.url))

/**
 * 旧版构建产物可能仍包含 require('../constants/punNav.js')；内联后 dist 易留空占位，微信报「module 未定义」。
 * 每次打包结束写入与当前页面约定一致的 CommonJS 导出。
 */
function mpWeixinPunNavCompat() {
  const source = `"use strict";
exports.PUN_NAV_HOME_SRC = "https://sofun.online/static/mini/home.png";
exports.PUN_NAV_ICON_HOME = "";
exports.PUN_NAV_ICON_BACK = "‹";
exports.PUN_PLAY_HINT_ICON_SRC = "https://sofun.online/static/mini/hintLabel.png";
exports.PUN_PLAY_SHARE_ICON_SRC = "https://sofun.online/static/mini/share.png";
`
  return {
    name: 'mp-weixin-pun-nav-compat',
    closeBundle() {
      for (const rel of ['dist/build/mp-weixin', 'dist/dev/mp-weixin']) {
        const dir = join(__dirname, rel, 'constants')
        const file = join(dir, 'punNav.js')
        try {
          mkdirSync(dir, { recursive: true })
          writeFileSync(file, source, 'utf8')
        } catch (e) {
          console.warn('[mp-weixin-pun-nav-compat]', rel, e)
        }
      }
    },
  }
}

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [uni(), mpWeixinPunNavCompat()],
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        silenceDeprecations: ['legacy-js-api'],
      },
    },
  },
})
