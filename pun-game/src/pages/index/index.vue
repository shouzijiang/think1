<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <!-- 顶部状态栏占位 -->
    <view :style="{ height: statusBarHeight + 'px', width: '100%' }"></view>

    <!-- 与 forum.vue 一致：nav-bar 高度 = 胶囊区；头像区尺寸 = nav-btn（menuButtonHeight） -->
    <view class="nav-bar" :style="{ height: navBarHeight + 'px' }">
      <!-- 用户头像与昵称（微信内可点击授权/修改，保存到本地与后端） -->
      <view class="user-header">
      <!-- #ifdef MP-WEIXIN -->
      <view class="user-info">
        <view class="avatar-wrapper">
          <button
            class="avatar-btn avatar-btn--nav"
            open-type="chooseAvatar"
            :style="navBtnSizeStyle"
            @chooseavatar="onChooseAvatar"
          >
            <image
              v-if="userInfo?.avatar"
              class="user-avatar"
              :src="userInfo.avatar"
              mode="aspectFill"
            />
            <view v-else class="user-avatar user-avatar-placeholder">👤</view>
          </button>
        </view>
        <view class="nickname-wrapper">
          <input
            type="nickname"
            class="nickname-input"
            placeholder="点击设置昵称"
            :value="userInfo?.nickname || ''"
            @blur="handleNicknameBlur"
            @confirm="handleNicknameConfirm"
          />
          <text class="edit-hint">点击昵称/头像即可修改</text>
        </view>
      </view>
      <!-- #endif -->
      <!-- #ifndef MP-WEIXIN -->
      <view class="user-info user-info-readonly">
        <view class="avatar-wrapper">
          <view class="avatar-fallback" :style="navBtnSizeStyle">
            <image
              v-if="userInfo?.avatar"
              class="user-avatar"
              :src="userInfo.avatar"
              mode="aspectFill"
            />
            <view v-else class="user-avatar user-avatar-placeholder">👤</view>
          </view>
        </view>
        <view class="nickname-wrapper">
          <text class="user-nickname">{{ userInfo?.nickname || '用户' }}</text>
        </view>
      </view>
      <!-- #endif -->
      </view>

      <view class="quota-corner">
        <view class="quota-corner-main">
          <text class="quota-corner-label">答案查看：</text>
          <view class="quota-corner-num-wrap">
            <text class="quota-corner-num">{{ hintAnswerQuota }}</text>
            <text class="quota-corner-unit">次</text>
          </view>
          <view class="quota-corner-tip" @click="showHintQuotaTip">
            <text class="quota-corner-tip-text">i</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 删除了原有的文字标题，改用统一的副标题 -->
    <view class="hero">
      <view class="hero-badge">
        <image
          class="hero-badge-img"
          src="/static/index-badge.jpg"
          mode="aspectFit"
        />
      </view>
      <text class="title">谐音梗猜一猜</text>
      <text class="subtitle">看图猜词 · 挑战你的脑洞</text>
    </view>

    <view class="start-wrap">
      <view class="btn-start btn-start-main" @click="startGameMid">
        <image
          class="btn-start-icon"
          src="/static/start.png"
          mode="aspectFit"
        />
        <text class="btn-start-text">开始游戏</text>
      </view>
      <view class="start-sub-row">
        <view class="btn-start btn-start-2" @click="startGame">
          <text class="btn-icon">🌱</text>
          <text class="btn-text">梗图填词</text>
        </view>
        <view class="btn-start btn-start-xhs" @click="startGameXhs">
          <image
            class="btn-icon btn-icon-img"
            src="/static/xhs.png"
            mode="aspectFit"
          />
          <text class="btn-text">小红书专辑</text>
        </view>
      </view>
    </view>

    <view class="top-actions">
      <view class="btn-entry" @click="goBattle">
        <image
          class="btn-icon btn-icon-img"
          src="/static/battle.png"
          mode="aspectFit"
        />
        <text class="btn-text">1V1对战</text>
      </view>
      <view class="btn-entry btn-levels" @click="goLevels">
        <image
          class="btn-icon btn-icon-img"
          src="/static/levels.png"
          mode="aspectFit"
        />
        <text class="btn-text">我的关卡</text>
      </view>
    </view>

    <view class="stats">
      <text class="stats-text">已有 {{ stats.players }} 位好友在玩 · 累计 {{ stats.answers }} 次答题</text>
    </view>

    <!-- 本期更新弹窗 -->
    <view v-if="changelogVisible" class="changelog-mask" @click="dismissChangelog">
      <view class="changelog-card" @click.stop>
        <text class="changelog-title">{{ changelogTitle }}</text>
        <scroll-view scroll-y class="changelog-scroll">
          <view v-for="(line, idx) in changelogLines" :key="idx" class="changelog-line-wrap">
            <text class="changelog-dot">•</text>
            <text class="changelog-line">{{ line }}</text>
          </view>
        </scroll-view>
        <view class="changelog-btn" @click="dismissChangelog">
          <text class="changelog-btn-text">知道了</text>
        </view>
      </view>
    </view>

    <view class="side-toolbar">
      <view class="toolbar-item" @click.stop="toggleBgm">
        <text class="toolbar-icon">{{ bgmOn ? '🎵' : '🔇' }}</text>
        <text class="toolbar-text">{{ bgmOn ? '音乐' : '静音' }}</text>
      </view>
      <view class="toolbar-item" @click.stop="goRank">
        <text class="toolbar-icon">🏆</text>
        <text class="toolbar-text">榜单</text>
      </view>
      <!-- <view class="toolbar-divider"></view>
      <view class="toolbar-item" @click="goForum">
        <text class="toolbar-icon">☕</text>
        <text class="toolbar-text">交流</text>
      </view> -->
      <view class="toolbar-divider"></view>
      <view class="toolbar-item" @click="goFeedback">
        <text class="toolbar-icon">📝</text>
        <text class="toolbar-text">反馈</text>
      </view>
      <!-- <view class="toolbar-divider"></view>
      <view class="toolbar-item" @click="goCocreate">
        <text class="toolbar-icon">💡</text>
        <text class="toolbar-text">共创</text>
      </view> -->
    </view>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow, onHide, onShareAppMessage, onShareTimeline } from '@dcloudio/uni-app'
import { getCurrentLevel, loadMidLevelList, loadXhsLevelList, pickMidLevelFromProgress, pickXhsLevelFromProgress } from '../../data/levels'
import { getUserInfo, wechatLogin } from '../../utils/auth'
import { api } from '../../utils/api'
import { useNavBar } from '../../composables/useNavBar'
import {
  isGameAudioEnabled,
  setGameAudioEnabled,
  preloadGameAudio,
  playBgmHome,
  stopBgm,
} from '../../utils/gameAudio'

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar()

/** 与 forum 页 nav-btn 一致：宽高等于微信胶囊按钮高度 */
const navBtnSizeStyle = computed(() => {
  const h = menuButtonHeight.value
  return {
    width: `${h}px`,
    height: `${h}px`,
  }
})

const CHANGELOG_SEEN_KEY = 'pun_changelog_seen_version'

const stats = ref({ players: 0, answers: 0 })
const userInfo = ref(null)
const hintAnswerQuota = ref(0)
const bgmOn = ref(isGameAudioEnabled())

const changelogVisible = ref(false)
const changelogTitle = ref('本期更新')
const changelogLines = ref([])
const changelogVersion = ref('')

function dismissChangelog() {
  if (changelogVersion.value) {
    uni.setStorageSync(CHANGELOG_SEEN_KEY, changelogVersion.value)
  }
  changelogVisible.value = false
}

async function loadHomeStats() {
  try {
    const data = await api.getHomeStats()
    if (!data || typeof data !== 'object') return
    const p = data.players ?? data.player_count
    const a = data.answers ?? data.answer_count
    stats.value = {
      players: typeof p === 'number' && !Number.isNaN(p) ? p : 0,
      answers: typeof a === 'number' && !Number.isNaN(a) ? a : 0,
    }
  } catch (e) {
    console.warn('home stats', e)
  }
}

async function tryShowChangelog() {
  try {
    const data = await api.getChangelogLatest()
    if (!data) return
    const versionCode = data.versionCode ?? data.version_code
    if (!versionCode) return
    const seen = uni.getStorageSync(CHANGELOG_SEEN_KEY) || ''
    if (String(seen) === String(versionCode)) return
    const lines = Array.isArray(data.lines) ? data.lines.filter(Boolean) : []
    if (lines.length === 0) return
    changelogTitle.value = data.title || '本期更新'
    changelogLines.value = lines
    changelogVersion.value = String(versionCode)
    changelogVisible.value = true
  } catch (e) {
    console.warn('changelog', e)
  }
}

function toggleBgm() {
  const next = !bgmOn.value
  bgmOn.value = next
  setGameAudioEnabled(next)
  if (next) {
    playBgmHome()
  } else {
    stopBgm()
  }
}

function loadUserInfo() {
  const info = getUserInfo()
  userInfo.value = info || { nickname: '', avatar: '', user_id: null, openid: '' }
}

function showHintQuotaTip() {
  uni.showModal({
    title: '答案次数说明',
    content: '每点一次「答案」消耗 1 次。分享至朋友圈/微信可增加次数。',
    showCancel: false,
  })
}

async function refreshHintAnswerQuota() {
  try {
    const data = await api.getLevelProgress({ gameTier: 'mid' })
    if (data && typeof data.hintAnswerQuota === 'number') {
      hintAnswerQuota.value = data.hintAnswerQuota
    }
  } catch {
    // 无 token / 未登录时忽略
  }
}

// 将图片转为 base64（微信小程序选头像后上传用）
function imageToBase64(filePath) {
  return new Promise((resolve, reject) => {
    // #ifdef MP-WEIXIN
    const fs = uni.getFileSystemManager()
    fs.readFile({
      filePath,
      encoding: 'base64',
      success: (res) => {
        const ext = (filePath.split('.').pop() || 'jpg').toLowerCase()
        const mime = ext === 'png' ? 'png' : 'jpeg'
        resolve(`data:image/${mime};base64,${res.data}`)
      },
      fail: (err) => {
        console.error('读取头像失败', err)
        reject(new Error('读取头像失败'))
      },
    })
    // #endif
    // #ifndef MP-WEIXIN
    reject(new Error('仅微信小程序支持'))
    // #endif
  })
}

// 微信：选择头像后保存到后端并更新本地
async function onChooseAvatar(e) {
  // #ifdef MP-WEIXIN
  const avatarUrl = e.detail.avatarUrl
  if (!avatarUrl) return
  const currentNickname = userInfo.value?.nickname || '微信用户'
  try {
    uni.showLoading({ title: '上传中…', mask: true })
    const avatarBase64 = await imageToBase64(avatarUrl)
    await api.updateUserInfo({ nickname: currentNickname, avatar: avatarBase64 })
    const updated = { ...userInfo.value, avatar: avatarUrl }
    uni.setStorageSync('userInfo', updated)
    userInfo.value = updated
    uni.hideLoading()
    uni.showToast({ title: '头像已更新', icon: 'success' })
  } catch (err) {
    uni.hideLoading()
    uni.showToast({ title: err.message || '更新失败', icon: 'none' })
  }
  // #endif
}

function handleNicknameBlur(e) {
  saveNickname(e.detail.value)
}
function handleNicknameConfirm(e) {
  saveNickname(e.detail.value)
}

async function saveNickname(nickname) {
  if (!userInfo.value) return
  const trimmed = (nickname || '').trim() || '微信用户'
  if (trimmed === userInfo.value.nickname) return
  const currentAvatar = userInfo.value.avatar || ''
  try {
    await api.updateUserInfo({ nickname: trimmed, avatar: currentAvatar })
    const updated = { ...userInfo.value, nickname: trimmed }
    uni.setStorageSync('userInfo', updated)
    userInfo.value = updated
    uni.showToast({ title: '昵称已保存', icon: 'success' })
  } catch (err) {
    uni.showToast({ title: err.message || '保存失败', icon: 'none' })
    loadUserInfo()
  }
}

onShow(async () => {
  preloadGameAudio()
  // #ifdef MP-WEIXIN
  try {
    // 确保登录完成后再读取本地 userInfo，避免 onShow 过早读取
    await wechatLogin()
  } catch (e) {
    // 登录失败也不阻断页面展示
    console.warn('wechatLogin 失败', e)
  }
  // #endif
  loadUserInfo()
  refreshHintAnswerQuota()
  bgmOn.value = isGameAudioEnabled()
  if (bgmOn.value) {
    playBgmHome()
  }
  loadHomeStats()
  tryShowChangelog()
})

onHide(() => {
  stopBgm()
})

// #ifdef MP-WEIXIN
onShareAppMessage(() => ({
  title: '谐音梗猜一猜，看图填词挑战脑洞！',
  path: '/pages/index/index',
}))
onShareTimeline(() => ({
  title: '谐音梗猜一猜 · 看图填词',
  query: '',
}))
// #endif

function goRank() {
  uni.navigateTo({ url: '/pages/rank/rank' })
}
function goLevels() {
  uni.navigateTo({ url: '/pages/levels/levels' })
}
function goForum() {
  uni.navigateTo({ url: '/pages/forum/forum' })
}
function goFeedback() {
  uni.navigateTo({ url: '/pages/feedback/feedback' })
}
function goCocreate() {
  // uni.navigateTo({ url: '/pages/cocreate/list' })
  uni.showToast({ title: '敬请期待~', icon: 'none' })
}

function goBattle() {
  uni.navigateTo({ url: '/pages/battleRoom/battleRoom' })
}

async function startGameXhs() {
  // uni.showToast({ title: '4月15号上线,尽情期待~', icon: 'none' })
  const goPlay = (lv) => { uni.navigateTo({ url: `/pages/playXhs/playXhs?level=${lv}` }) }
  try {
    const data = await api.getLevelProgress({ gameTier: 'xhs' })
    const list = await loadXhsLevelList()
    const totalRaw = data && data.totalLevels != null ? Number(data.totalLevels) : list.length
    const total = Number.isFinite(totalRaw) && totalRaw > 0 ? totalRaw : list.length
    const passedCount = data && Array.isArray(data.passedLevels)
      ? new Set(
        data.passedLevels
          .map((x) => Number(x))
          .filter((x) => Number.isFinite(x) && list.includes(x))
      ).size
      : 0
    if (total > 0 && passedCount >= total) {
      uni.showToast({ title: '专辑持续更新中，敬请期待~', icon: 'none' })
      return
    }
    const lv = pickXhsLevelFromProgress(data)
    goPlay(lv != null ? lv : 1)
  } catch {
    goPlay(1)
  }
}
async function startGameMid() {
  const goPlay = (lv) => { uni.navigateTo({ url: `/pages/playMid/playMid?level=${lv}` }) }
  try {
    const data = await api.getLevelProgress({ gameTier: 'mid' })
    const list = await loadMidLevelList()
    const totalRaw = data && data.totalLevels != null ? Number(data.totalLevels) : list.length
    const total = Number.isFinite(totalRaw) && totalRaw > 0 ? totalRaw : list.length
    const passedCount = data && Array.isArray(data.passedLevels)
      ? new Set(
        data.passedLevels
          .map((x) => Number(x))
          .filter((x) => Number.isFinite(x) && list.includes(x))
      ).size
      : 0
    if (total > 0 && passedCount >= total) {
      uni.showToast({ title: '关卡持续更新中,敬请期待,您可以前往我的关卡继续游玩~', icon: 'none' })
      return
    }
    const lv = pickMidLevelFromProgress(data)
    goPlay(lv != null && lv > 0 ? lv : 0)
  } catch {
    goPlay(0)
  }
}

function startGame() {
  const goPlay = (level) => { uni.navigateTo({ url: `/pages/play/play?level=${level}` }) }
  api.getLevelProgress()
    .then((data) => {
      if(data.currentLevel >= 270) {
        uni.showToast({ title: '关卡持续更新中,敬请期待,您可以前往我的关卡继续游玩~', icon: 'none' })
        return
      }
      goPlay(data.currentLevel != null ? data.currentLevel : 1)
    })
    .catch(() => goPlay(getCurrentLevel()))
}
</script>

<style lang="scss" scoped>
/* 参考：浅粉蓝底 + 薄荷绿主色 + 浅蓝描边（柔和不刺眼） */
.page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  overflow: hidden;
  padding: 0 40rpx;
  box-sizing: border-box;
  background: #eaf6f9;
}

.bg-wrap {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}
.bg-gradient {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 200rpx 120rpx at 12% 22%, rgba(255, 235, 200, 0.45), transparent 55%),
    radial-gradient(ellipse 180rpx 100rpx at 88% 18%, rgba(210, 200, 245, 0.35), transparent 50%),
    radial-gradient(ellipse 220rpx 140rpx at 72% 78%, rgba(255, 210, 190, 0.28), transparent 55%),
    linear-gradient(180deg, #eaf6f9 0%, #e4f3f8 45%, #eaf6f9 100%);
}
.bg-dots {
  position: absolute;
  inset: 0;
  opacity: 0.35;
  background-image: radial-gradient(circle at 2px 2px, rgba(169, 201, 238, 0.45) 2px, transparent 0);
  background-size: 40rpx 40rpx;
}
.bg-glow {
  position: absolute;
  top: -5%;
  left: 50%;
  transform: translateX(-50%);
  width: 120%;
  height: 50%;
  background: radial-gradient(
    ellipse at center,
    rgba(255, 255, 255, 0.65) 0%,
    rgba(234, 246, 249, 0) 68%
  );
  pointer-events: none;
}

/* 与 forum.vue .nav-bar 同构：条内左侧区域对齐「返回/首页」nav-btn 位置 */
.nav-bar {
  position: relative;
  z-index: 2;
  width: 100%;
  box-sizing: border-box;
  margin-bottom: 24rpx;
}

.quota-corner {
  box-sizing: border-box;
  position: absolute;
  left: -20rpx;
  top: 120%;
  height: 100%;
  min-width: 220rpx;
  padding: 40rpx 20rpx;
  border-radius: 50rpx;
  background: rgba(255, 255, 255, 0.78);
  border: 2rpx solid rgba(180, 200, 230, 0.45);
  box-shadow: 0 6rpx 18rpx rgba(100, 140, 180, 0.12);
  display: flex;
  flex-direction: column;
  justify-content: center;
  box-sizing: border-box;
  border-top: none;
}
.quota-corner-main {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
}
.quota-corner-label {
  font-size: 24rpx;
  color: #6d8192;
  font-weight: 700;
}
.quota-corner-num-wrap {
  display: flex;
  align-items: baseline;
  gap: 4rpx;
}
.quota-corner-num {
  font-size: 34rpx;
  line-height: 1;
  font-weight: 900;
  color: #16a34a;
  font-variant-numeric: tabular-nums;
}
.quota-corner-unit {
  font-size: 20rpx;
  color: #16a34a;
  font-weight: 800;
  margin-right: 10rpx;
}
.quota-corner-tip {
  width: 28rpx;
  height: 28rpx;
  border-radius: 50%;
  background: rgba(47, 122, 217, 0.14);
  border: 1rpx solid rgba(47, 122, 217, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
}
.quota-corner-tip-text {
  font-size: 20rpx;
  line-height: 1;
  color: #2f7ad9;
  font-weight: 800;
}

.user-header {
  position: absolute;
  left: -20rpx;
  top: 60%;
  transform: translateY(-50%);
  z-index: 2;
  display: flex;
  align-items: center;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.user-info {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 20rpx;
  max-width: 100%;
  padding: 8rpx 20rpx 8rpx 12rpx;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 100rpx;
  // box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.22);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
  box-sizing: border-box;
}
.user-info-readonly {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 20rpx;
  max-width: 100%;
  padding: 8rpx 20rpx 8rpx 12rpx;
  border-radius: 100rpx;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 2rpx solid rgba(169, 201, 238, 0.55);
  box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.22);
  box-sizing: border-box;
}
.avatar-wrapper {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}
.avatar-btn {
  padding: 0;
  margin: 0;
  background: transparent;
  border: none;
  line-height: 1;
  position: relative;
}
.avatar-btn::after {
  border: none;
}
/* 对齐 forum .nav-btn：固定为 menuButtonHeight 正方形 */
.avatar-btn--nav {
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.92);
  border: 2rpx solid rgba(169, 201, 238, 0.65);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.2);
}
.avatar-fallback {
  flex-shrink: 0;
  box-sizing: border-box;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.92);
  border: 2rpx solid rgba(169, 201, 238, 0.65);
  box-shadow: 0 4rpx 14rpx rgba(169, 201, 238, 0.2);
}
.avatar-btn--nav .user-avatar,
.avatar-fallback .user-avatar {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  border: none;
  display: block;
}
.user-avatar-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  font-size: 48rpx;
  background: rgba(234, 246, 249, 0.95);
  filter: saturate(0.88);
}
.nickname-wrapper {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4rpx;
}
.nickname-input {
  width: 100%;
  font-size: 28rpx;
  font-weight: 700;
  color: #5a6d7a;
  background: transparent;
  border: none;
  padding: 0;
  margin: 0;
  height: auto;
  line-height: 1.2;
}
.nickname-input::placeholder {
  color: #8eadcf;
  font-weight: 600;
}
.edit-hint {
  font-size: 20rpx;
  color: #8eadcf;
  font-weight: 400;
  border-radius: 12rpx;
  display: inline-block;
  align-self: flex-start;
}
.user-nickname {
  flex: 1;
  font-size: 28rpx;
  font-weight: 700;
  color: #5a6d7a;
}

.hero {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 4vh 0 9vh;
  animation: float 3s ease-in-out infinite;
}
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-12rpx); }
}
.hero-badge {
  width: 200rpx;
  height: 200rpx;
  border-radius: 36rpx;
  background: rgba(255, 255, 255, 0.55);
  border: 6rpx solid rgba(255, 255, 255, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 24rpx auto 30rpx;
  box-shadow: 0 12rpx 32rpx rgba(169, 201, 238, 0.35), 0 4rpx 0 rgba(255, 255, 255, 0.8) inset;
  transform: rotate(-4deg);
  overflow: hidden;
  box-sizing: border-box;
}
.hero-badge-img {
  width: 100%;
  height: 100%;
}
.hero-emoji {
  font-size: 100rpx;
  filter: drop-shadow(0 8rpx 8rpx rgba(0,0,0,0.1));
}
.title {
  font-size: 56rpx;
  font-weight: 900;
  color: #91d58b;
  letter-spacing: 0.08em;
  margin-bottom: 12rpx;
  text-shadow: 0 3rpx 0 rgba(111, 184, 104, 0.35), 0 6rpx 18rpx rgba(145, 213, 139, 0.2);
}
.subtitle {
  font-size: 24rpx;
  font-weight: 600;
  color: #8eadcf;
  letter-spacing: 0.06em;
  background: rgba(255, 255, 255, 0.72);
  padding: 10rpx 26rpx;
  border-radius: 100rpx;
  border: 2rpx solid rgba(169, 201, 238, 0.45);
}

.start-wrap {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 530rpx;
  display: flex;
  flex-direction: column;
  gap: 32rpx;
  margin-bottom: 50rpx;
}
.start-sub-row {
  width: 100%;
  display: flex;
  gap: 16rpx;
}
.btn-start {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14rpx;
  padding: 28rpx 40rpx;
  border-radius: 28rpx;
  font-size: 30rpx;
  font-weight: 700;
  letter-spacing: 0.04em;
  transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  overflow: hidden;
  box-sizing: border-box;

  /* 主按钮：薄荷绿实底 + 白字 */
  background: linear-gradient(180deg, #a3dd9c 0%, #91d58b 55%, #85c97f 100%);
  color: #fff;
  border: 2rpx solid rgba(255, 255, 255, 0.68);
  box-shadow:
    0 22rpx 32rpx rgba(145, 213, 139, 0.38),
    0 12rpx 0 rgba(72, 145, 68, 0.34),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.4),
    inset 0 -10rpx 14rpx rgba(0, 0, 0, 0.16);

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 58%;
    background: linear-gradient(
      to bottom,
      rgba(255, 255, 255, 0.5) 0%,
      rgba(255, 255, 255, 0.22) 36%,
      transparent 100%
    );
    // border-radius: 999rpx 999rpx 0 0;
    pointer-events: none;
  }
  &::after {
    content: '';
    position: absolute;
    left: 8%;
    bottom: 12%;
    width: 42%;
    height: 24%;
    border-radius: 120rpx;
    background: radial-gradient(
      ellipse at center,
      rgba(255, 255, 255, 0.34) 0%,
      rgba(255, 255, 255, 0.08) 55%,
      rgba(255, 255, 255, 0) 100%
    );
    pointer-events: none;
  }

  /* 经典：青绿主色 rgb(102, 222, 209) */
  &.btn-start-2 {
    background: linear-gradient(
      180deg,
      rgb(142, 236, 224) 0%,
      rgb(102, 222, 209) 55%,
      rgb(72, 200, 186) 100%
    );
    color: #fff;
    border: 2rpx solid rgba(255, 255, 255, 0.64);
    box-shadow:
      0 20rpx 30rpx rgba(102, 222, 209, 0.42),
      0 12rpx 0 rgba(38, 156, 144, 0.3),
      inset 0 2rpx 0 rgba(255, 255, 255, 0.36),
      inset 0 -10rpx 14rpx rgba(0, 0, 0, 0.14);
  }

  &.btn-start-xhs {
    background: linear-gradient(
      180deg,
      rgb(255, 216, 229) 0%,
      rgb(245, 175, 200) 55%,
      rgb(231, 139, 174) 100%
    );
    color: #fff;
    border: 2rpx solid rgba(255, 255, 255, 0.64);
    box-shadow:
      0 20rpx 30rpx rgba(245, 137, 163, 0.36),
      0 12rpx 0 rgba(189, 90, 126, 0.26),
      inset 0 2rpx 0 rgba(255, 255, 255, 0.35),
      inset 0 -10rpx 14rpx rgba(0, 0, 0, 0.14);

    .btn-icon-img {
      width: 40rpx;
      height: 40rpx;
    }
  }
}
.btn-start-main {
  width: 100%;
  padding: 36rpx 40rpx;
  font-size: 40rpx;
  // 字间距
  letter-spacing: 0.2em;
}
.start-sub-row .btn-start {
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 12rpx;
  padding: 24rpx 18rpx;
  font-size: 26rpx;
}
.btn-start:active {
  transform: translateY(10rpx) scale(0.985);
  filter: brightness(0.97);
  box-shadow:
    0 4rpx 8rpx rgba(0, 0, 0, 0.2),
    0 1rpx 0 rgba(0, 0, 0, 0.12),
    inset 0 3rpx 0 rgba(255, 255, 255, 0.2),
    inset 0 -8rpx 12rpx rgba(0, 0, 0, 0.2);
}
.btn-start-icon {
  width: 44rpx;
  height: 44rpx;
  flex-shrink: 0;
  filter: drop-shadow(0 3rpx 6rpx rgba(0, 0, 0, 0.16));
}
.btn-start-text {
  text-shadow: 0 3rpx 8rpx rgba(0, 0, 0, 0.2);
}

.btn-cocreate-text-sub {
  font-size: 26rpx;
  font-weight: 600;
  color: #64748b;
  padding: 12rpx 32rpx;
  background: rgba(255, 255, 255, 0.6);
  border-radius: 100rpx;
  transition: all 0.1s;
}
.btn-cocreate-text-sub:active {
  background: rgba(255, 255, 255, 0.9);
  transform: scale(0.95);
}

.top-actions {
  position: relative;
  z-index: 2;
  display: flex;
  gap: 24rpx;
  width: 100%;
  max-width: 530rpx;
}
.btn-entry {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12rpx;
  padding: 26rpx 18rpx;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(10px);
  border-radius: 24rpx;
  color: #8eadcf;
  font-size: 26rpx;
  font-weight: 700;
  box-shadow:
    0 18rpx 24rpx rgba(169, 201, 238, 0.26),
    0 10rpx 0 rgba(117, 154, 194, 0.28),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.5),
    inset 0 -8rpx 12rpx rgba(54, 84, 120, 0.12);
  border: 2rpx solid rgba(169, 201, 238, 0.62);
  transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  overflow: hidden;
  position: relative;
}
.btn-entry::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 56%;
  background: linear-gradient(
    to bottom,
    rgba(255, 255, 255, 0.52) 0%,
    rgba(255, 255, 255, 0.18) 36%,
    transparent 100%
  );
  pointer-events: none;
}
.btn-entry::after {
  content: '';
  position: absolute;
  left: 10%;
  bottom: 12%;
  width: 44%;
  height: 22%;
  border-radius: 100rpx;
  background: radial-gradient(
    ellipse at center,
    rgba(255, 255, 255, 0.3) 0%,
    rgba(255, 255, 255, 0.08) 58%,
    rgba(255, 255, 255, 0) 100%
  );
  pointer-events: none;
}
.btn-entry:active {
  transform: translateY(8rpx) scale(0.985);
  filter: brightness(0.97);
  box-shadow:
    0 4rpx 8rpx rgba(84, 116, 154, 0.24),
    0 1rpx 0 rgba(84, 116, 154, 0.16),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.24),
    inset 0 -7rpx 10rpx rgba(54, 84, 120, 0.2);
}
.btn-icon {
  font-size: 36rpx;
  line-height: 1.2;
  filter: saturate(0.95) brightness(1.03) drop-shadow(0 3rpx 6rpx rgba(54, 84, 120, 0.2));
}
.btn-icon-img {
  width: 40rpx;
  height: 40rpx;
  flex-shrink: 0;
  filter: drop-shadow(0 3rpx 6rpx rgba(54, 84, 120, 0.2));
}
.btn-text {
  font-size: 26rpx;
  font-weight: 700;
  line-height: 1.2;
  text-shadow: 0 2rpx 6rpx rgba(54, 84, 120, 0.18);
}

.stats {
  position: relative;
  z-index: 2;
  margin-top: auto;
  padding: 32rpx;
  margin-bottom: 20rpx;
}
.stats-text {
  font-size: 22rpx;
  font-weight: 600;
  color: #8eadcf;
  letter-spacing: 0.03em;
  background: rgba(255, 255, 255, 0.65);
  padding: 10rpx 26rpx;
  border-radius: 100rpx;
  border: 2rpx solid rgba(169, 201, 238, 0.4);
}

.side-toolbar {
  position: fixed;
  right: 0rpx;
  bottom: 220rpx;
  z-index: 50;
  display: flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255, 255, 255, 0.82);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 60rpx;
  border: 3rpx solid rgba(169, 201, 238, 0.45);
  box-shadow: 0 8rpx 24rpx rgba(169, 201, 238, 0.2);
  padding: 20rpx 0;
}

.toolbar-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100rpx;
  height: 100rpx;
  gap: 6rpx;
  transition: all 0.15s ease;
}

.toolbar-item:active {
  transform: scale(0.9);
  opacity: 0.8;
}

.toolbar-divider {
  width: 50rpx;
  height: 2rpx;
  background: rgba(169, 201, 238, 0.35);
  margin: 8rpx 0;
}

.toolbar-icon {
  font-size: 34rpx;
  line-height: 1;
  filter: saturate(0.88);
  opacity: 0.92;
}

.toolbar-text {
  font-size: 20rpx;
  color: #8eadcf;
  font-weight: 600;
}

.changelog-mask {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(90, 120, 130, 0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48rpx;
  box-sizing: border-box;
}
.changelog-card {
  width: 100%;
  max-width: 600rpx;
  max-height: 70vh;
  background: rgba(255, 255, 255, 0.96);
  border-radius: 32rpx;
  padding: 40rpx 36rpx 32rpx;
  box-shadow: 0 16rpx 40rpx rgba(169, 201, 238, 0.35);
  border: 3rpx solid rgba(169, 201, 238, 0.4);
  display: flex;
  flex-direction: column;
}
.changelog-title {
  font-size: 34rpx;
  font-weight: 800;
  color: #6fb868;
  text-align: center;
  margin-bottom: 28rpx;
}
.changelog-scroll {
  flex: 1;
  max-height: 48vh;
  margin-bottom: 28rpx;
}
.changelog-line-wrap {
  display: flex;
  align-items: flex-start;
  gap: 12rpx;
  margin-bottom: 16rpx;
}
.changelog-dot {
  font-size: 26rpx;
  color: #91d58b;
  line-height: 1.5;
  flex-shrink: 0;
}
.changelog-line {
  flex: 1;
  font-size: 26rpx;
  line-height: 1.55;
  color: #6a7f8c;
  font-weight: 600;
}
.changelog-btn {
  align-self: center;
  padding: 18rpx 72rpx;
  background: linear-gradient(180deg, #a3dd9c 0%, #91d58b 100%);
  border-radius: 100rpx;
  border: 3rpx solid rgba(255, 255, 255, 0.5);
  box-shadow: 0 6rpx 18rpx rgba(145, 213, 139, 0.35);
}
.changelog-btn:active {
  opacity: 0.92;
  transform: scale(0.98);
}
.changelog-btn-text {
  font-size: 28rpx;
  font-weight: 700;
  color: #fff;
}

</style>
