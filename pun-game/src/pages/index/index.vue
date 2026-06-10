<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
      <view class="bg-glow" />
    </view>

    <!-- 顶部状态栏占位 -->
    <view :style="{ height: statusBarHeight + 'px', width: '100%' }"></view>

    <view :style="{ height: navBarHeight + 'px', width: '100%' }"></view>

    <!-- 删除了原有的文字标题，改用统一的副标题 -->
    <view class="hero">
      <!-- 发光脉冲环 -->
      <view class="hero-aura">
        <view class="hero-aura-ring hero-aura-ring--1" />
        <view class="hero-aura-ring hero-aura-ring--2" />
        <view class="hero-aura-ring hero-aura-ring--3" />
      </view>
      <!-- 装饰粒子 -->
      <view class="hero-particles">
        <view class="hero-particle hero-particle--1">✦</view>
        <view class="hero-particle hero-particle--2">✧</view>
        <view class="hero-particle hero-particle--3">✦</view>
        <view class="hero-particle hero-particle--4">✧</view>
        <view class="hero-particle hero-particle--5">·</view>
        <view class="hero-particle hero-particle--6">·</view>
      </view>
      <view class="hero-badge">
        <image
          class="hero-badge-img"
          src="https://sofun.online/static/mini/index-badge.jpg"
          mode="aspectFit"
        />
      </view>
      <text class="title">谐音梗猜一猜</text>
      <text class="subtitle">看图猜词 · 挑战你的脑洞</text>
    </view>

    <view class="start-wrap">
      <view
        class="btn-start btn-start-main"
        hover-class="btn-start--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="startGameXhs"
      >
        <text class="btn-start-text btn-start-text1">开始游戏</text>
        <text class="btn-start-text btn-start-text2">(小红书专辑-高级难度)</text>
      </view>
      <view class="start-sub-row">
        <view
          class="btn-start btn-start-xhs"
          hover-class="btn-start--hover"
          :hover-start-time="20"
          :hover-stay-time="100"
          @click="startGameMid"
        >
          <image
            class="btn-icon btn-icon-img"
            src="https://sofun.online/static/mini/xhs.png"
            mode="aspectFit"
          />
          <text class="btn-text">经典专辑</text>
        </view>
        <view
          class="btn-start btn-start-2"
          hover-class="btn-start--hover"
          :hover-start-time="20"
          :hover-stay-time="100"
          @click="startGame"
        >
          <image
            class="btn-icon btn-icon-img"
            src="https://sofun.online/static/mini/gttc.png"
            mode="aspectFit"
          />
          <text class="btn-text">梗图填词</text>
        </view>
      </view>
    </view>

    <view class="top-actions">
      <view
        class="btn-entry btn-entry--battle"
        hover-class="btn-entry--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="goBattle"
      >
        <view class="battle-badge">3分钟一局</view>
        <image
          class="btn-icon btn-icon-img"
          src="https://sofun.online/static/mini/battle.png"
          mode="aspectFit"
        />
        <text class="btn-text">邀请好友</text>
        <text class="btn-sub-text">1V1对战</text>
      </view>
      <view
        class="btn-entry btn-levels"
        hover-class="btn-entry--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="goLevels"
      >
        <text class="btn-icon">📋</text>
        <text class="btn-text">我的关卡</text>
      </view>
    </view>

    <view class="stats">
      <text class="stats-text"
        >已有 {{ stats.players }} 位好友在玩 · 累计 {{ stats.answers }} 次答题</text
      >
    </view>

    <!-- 本期更新弹窗 -->
    <view v-if="changelogVisible" class="changelog-mask" @click="dismissChangelog">
      <view class="changelog-card" @click.stop>
        <text class="changelog-title">{{ changelogTitle }}</text>
        <scroll-view scroll-y class="changelog-scroll">
          <view
            v-for="(line, idx) in changelogLines"
            :key="idx"
            class="changelog-line-wrap"
          >
            <text class="changelog-dot">{{ idx + 1 }}.</text>
            <text class="changelog-line">{{ line }}</text>
          </view>
        </scroll-view>
        <view
          class="changelog-suppress-row"
          @click.stop="changelogSuppressChecked = !changelogSuppressChecked"
        >
          <view
            :class="[
              'changelog-suppress-check',
              { 'changelog-suppress-check--on': changelogSuppressChecked },
            ]"
          >
            <text v-if="changelogSuppressChecked" class="changelog-suppress-tick">✓</text>
          </view>
          <text class="changelog-suppress-label">7天内不再展示</text>
        </view>
        <view
          class="changelog-btn"
          hover-class="changelog-btn--hover"
          :hover-start-time="20"
          :hover-stay-time="100"
          @click="dismissChangelog"
        >
          <text class="changelog-btn-text">知道了</text>
        </view>
      </view>
    </view>

    <view class="side-toolbar">
      <view
        class="toolbar-item"
        hover-class="toolbar-item--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click.stop="toggleBgm"
      >
        <image
          v-if="bgmOn"
          class="btn-start-icon"
          src="https://sofun.online/static/mini/bgm.png"
          mode="aspectFit"
        />
        <image
          v-else
          class="btn-start-icon"
          src="https://sofun.online/static/mini/close-yy.png"
          mode="aspectFit"
        />
        <text class="toolbar-text">音乐</text>
      </view>
      <view class="toolbar-divider"></view>
      <view
        class="toolbar-item"
        hover-class="toolbar-item--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click.stop="toggleSfx"
      >
        <image
          v-if="sfxOn"
          class="btn-start-icon"
          src="https://sofun.online/static/mini/sfx.png"
          mode="aspectFit"
        />
        <image
          v-else
          class="btn-start-icon"
          src="https://sofun.online/static/mini/close-yy.png"
          mode="aspectFit"
        />
        <text class="toolbar-text">音效</text>
      </view>
      <view class="toolbar-divider"></view>
      <view
        class="toolbar-item"
        hover-class="toolbar-item--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click="goRank"
      >
        <image
          class="btn-start-icon"
          src="https://sofun.online/static/mini/rank.png"
          mode="aspectFit"
        />
        <text class="toolbar-text">榜单</text>
      </view>
      <view class="toolbar-divider"></view>
      <view
        class="toolbar-item"
        hover-class="toolbar-item--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click.stop="goMail"
      >
        <image
          class="btn-start-icon"
          src="https://sofun.online/static/mini/mail.png"
          mode="aspectFit"
        />
        <text class="toolbar-text">邮件</text>
      </view>
      <view class="toolbar-divider"></view>
      <view
        class="toolbar-item toolbar-item--tasks-highlight"
        hover-class="toolbar-item--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click.stop="goTasks"
      >
        <view class="toolbar-tasks-badge">H</view>
        <image
          class="btn-start-icon"
          src="https://sofun.online/static/mini/tasks.png"
          mode="aspectFit"
        />
        <text class="toolbar-text">任务</text>
      </view>
      <view class="toolbar-divider"></view>
      <view
        class="toolbar-item"
        hover-class="toolbar-item--hover"
        :hover-start-time="20"
        :hover-stay-time="100"
        @click.stop="goMine"
      >
        <image
          class="btn-start-icon"
          src="https://sofun.online/static/mini/mine.png"
          mode="aspectFit"
        />
        <text class="toolbar-text">我的</text>
      </view>
    </view>

    <view
      class="streamer-float"
      hover-class="streamer-float--hover"
      :hover-start-time="20"
      :hover-stay-time="100"
      @click="goStreamer"
    >
      <view class="streamer-float-inner">
        <image
          class="btn-start-icon"
          src="https://sofun.online/static/mini/gold.png"
          mode="aspectFit"
        />
        <text class="streamer-float-title">邀好友</text>
        <text class="streamer-float-sub">赚收益</text>
      </view>
      <view class="streamer-float-dot" />
    </view>
  </view>
</template>

<script setup>
import { ref } from "vue";
import {
  onLoad,
  onShow,
  onHide,
  onUnload,
  onShareAppMessage,
  onShareTimeline,
} from "@dcloudio/uni-app";
import {
  installMpAppWxListeners,
  uninstallMpAppWxListeners,
} from "../../utils/mpAppWxListeners";
import {
  getCurrentLevel,
  loadMidLevelList,
  loadXhsLevelList,
  pickMidLevelFromProgress,
  pickXhsLevelFromProgress,
} from "../../data/levels";
import { wechatLogin } from "../../utils/auth";
import { api } from "../../utils/api";
import {
  setPendingChannel,
  peekPendingChannel,
  clearPendingChannel,
  getJsonStorageCached,
  setJsonStorageCached,
  warmStartupStorage,
} from "../../utils/storageCache";
import { useNavBar } from "../../composables/useNavBar";
import {
  isBgmEnabled,
  isSfxEnabled,
  setBgmEnabled,
  setSfxEnabled,
  playBgmHome,
  stopBgm,
} from "../../utils/gameAudio";
import { usePunShareReward } from "../../composables/usePunShareReward";

const { statusBarHeight, navBarHeight } = useNavBar();

const hintShareQuotaRef = ref(0);
const { withShareReward } = usePunShareReward(hintShareQuotaRef);

const CHANGELOG_SEEN_KEY = "pun_changelog_suppress"; // { version, until }
let indexBgmBootstrapped = false;
/** @type {ReturnType<typeof setTimeout>[]} */
const indexPendingTimers = [];
/** 页面销毁标记：防止异步回调在 onUnload 后继续修改页面状态 */
let indexPageDestroyed = false;

// 捕获买量渠道参数（?channel=xxx 或扫码 scene=xxx）
onLoad((opts) => {
  // wx.onAppRouteDone 由本页面持有：onLoad 注册，onUnload 移除，避免 reLaunch 后残留
  installMpAppWxListeners();

  let channel = opts?.channel || "";
  if (!channel && opts?.scene) {
    try {
      const decoded = decodeURIComponent(opts.scene);
      const match = decoded.match(/channel=([^&]+)/);
      if (match) channel = match[1];
    } catch {}
  }
  if (channel) {
    setPendingChannel(channel);
  }
});

const stats = ref({ players: 0, answers: 0 });
/** 首屏先用默认值渲染，storage 预热后再同步真实开关 */
const bgmOn = ref(true);
const sfxOn = ref(true);

const changelogVisible = ref(false);
const changelogTitle = ref("本期更新");
const changelogLines = ref([]);
const changelogVersion = ref("");
const changelogSuppressChecked = ref(false);

function dismissChangelog() {
  if (changelogVersion.value && changelogSuppressChecked.value) {
    const until = Date.now() + 7 * 24 * 60 * 60 * 1000;
    setJsonStorageCached(CHANGELOG_SEEN_KEY, {
      version: changelogVersion.value,
      until,
    });
  }
  changelogVisible.value = false;
}

async function loadHomeStats() {
  try {
    const data = await api.getHomeStats();
    if (!data || typeof data !== "object") return;
    const p = data.players ?? data.player_count;
    const a = data.answers ?? data.answer_count;
    stats.value = {
      players: typeof p === "number" && !Number.isNaN(p) ? p : 0,
      answers: typeof a === "number" && !Number.isNaN(a) ? a : 0,
    };
  } catch (e) {
    console.warn("home stats", e);
  }
}

async function tryShowChangelog() {
  try {
    const data = await api.getChangelogLatest();
    if (!data) return;
    const versionCode = data.versionCode ?? data.version_code;
    if (!versionCode) return;
    const lines = Array.isArray(data.lines) ? data.lines.filter(Boolean) : [];
    if (lines.length === 0) return;

    // 检查是否在 7 天内已勾选不展示（且是同一版本）
    const saved = getJsonStorageCached(CHANGELOG_SEEN_KEY);
    if (
      saved &&
      saved.version === String(versionCode) &&
      typeof saved.until === "number" &&
      Date.now() < saved.until
    ) {
      return; // 7 天内且同版本 → 不展示
    }

    changelogSuppressChecked.value = false;
    changelogTitle.value = data.title || "本期更新";
    changelogLines.value = lines;
    changelogVersion.value = String(versionCode);
    changelogVisible.value = true;
  } catch (e) {
    console.warn("changelog", e);
  }
}

function toggleBgm() {
  const next = !bgmOn.value;
  bgmOn.value = next;
  setBgmEnabled(next);
  if (next) {
    playBgmHome();
  } else {
    stopBgm();
  }
}

function toggleSfx() {
  const next = !sfxOn.value;
  sfxOn.value = next;
  setSfxEnabled(next);
}

function runIndexAfterStorageWarm() {
  if (indexPageDestroyed) return;

  bgmOn.value = isBgmEnabled();
  sfxOn.value = isSfxEnabled();

  if (!indexBgmBootstrapped) {
    indexBgmBootstrapped = true;
    indexPendingTimers.push(
      setTimeout(() => {
        if (isBgmEnabled()) playBgmHome();
      }, 1200)
    );
  } else if (bgmOn.value) {
    playBgmHome();
  }

  wechatLogin()
    .then(() => {
      const pendingChannel = peekPendingChannel();
      if (pendingChannel) {
        api.reportChannel(pendingChannel).catch(() => {});
        clearPendingChannel();
      }
    })
    .catch((e) => {
      console.warn("wechatLogin 失败", e);
    });
}

onShow(() => {
  indexPendingTimers.push(
    setTimeout(() => {
      warmStartupStorage().then(() => {
        if (indexPageDestroyed) return;
        runIndexAfterStorageWarm();
      });
    }, 0)
  );

  indexPendingTimers.push(
    setTimeout(() => {
      warmStartupStorage().then(() => {
        if (indexPageDestroyed) return;
        loadHomeStats();
        tryShowChangelog();
      });
    }, 300)
  );
});

/** 仅清除页面定时器，不触碰 App 级 wx 监听器（页面 onHide 不代表销毁） */
function clearIndexPageTimersOnly() {
  indexPendingTimers.forEach((id) => clearTimeout(id));
  indexPendingTimers.length = 0;
}

/** 页面真正销毁时：清除定时器 + 移除本页注册的 wx 监听器（不重新注册，由下一次 onLoad 接管） */
function clearIndexPageSideEffects() {
  indexPageDestroyed = true;
  indexPendingTimers.forEach((id) => clearTimeout(id));
  indexPendingTimers.length = 0;
  uninstallMpAppWxListeners();
}

onHide(() => {
  clearIndexPageTimersOnly();
});

onUnload(() => {
  clearIndexPageSideEffects();
});

onShareAppMessage(() =>
  withShareReward({
    title: "谐音梗猜一猜，看图填词挑战脑洞！",
    path: "/pages/index/index",
  })
);
onShareTimeline(() => ({
  title: "谐音梗猜一猜 · 看图填词",
  query: "",
}));

function goRank() {
  uni.navigateTo({ url: "/pages-sub/rank/rank" });
}
function goMine() {
  uni.navigateTo({ url: "/pages-sub/mine/mine" });
}
function goTasks() {
  uni.navigateTo({ url: "/pages-sub/tasks/tasks" });
}
function goMail() {
  uni.navigateTo({ url: "/pages-sub/mail/mail" });
}
function goForum() {
  uni.navigateTo({ url: "/pages-sub/forum/forum" });
}
function goLevels() {
  uni.navigateTo({ url: "/pages-sub/levels/levels" });
}

function goBattle() {
  uni.navigateTo({ url: "/pages-sub/battleRoom/battleRoom" });
}

function goStreamer() {
  uni.navigateTo({ url: "/pages-sub/streamer/streamer" });
}

async function startGameXhs() {
  // uni.showToast({ title: '4月15号上线,尽情期待~', icon: 'none' })
  const goPlay = (lv) => {
    uni.navigateTo({ url: `/pages-sub/playXhs/playXhs?level=${lv}` });
  };
  try {
    const data = await api.getLevelProgress({ gameTier: "xhs" });
    const list = await loadXhsLevelList();
    const totalRaw =
      data && data.totalLevels != null ? Number(data.totalLevels) : list.length;
    const total = Number.isFinite(totalRaw) && totalRaw > 0 ? totalRaw : list.length;
    const passedCount =
      data && Array.isArray(data.passedLevels)
        ? new Set(
            data.passedLevels
              .map((x) => Number(x))
              .filter((x) => Number.isFinite(x) && list.includes(x))
          ).size
        : 0;
    if (total > 0 && passedCount >= total) {
      uni.showToast({ title: "专辑持续更新中，敬请期待~", icon: "none" });
      return;
    }
    const lv = pickXhsLevelFromProgress(data);
    goPlay(lv != null ? lv : 1);
  } catch {
    goPlay(1);
  }
}
async function startGameMid() {
  const goPlay = (lv) => {
    uni.navigateTo({ url: `/pages-sub/playMid/playMid?level=${lv}` });
  };
  try {
    const data = await api.getLevelProgress({ gameTier: "mid" });
    const list = await loadMidLevelList();
    const totalRaw =
      data && data.totalLevels != null ? Number(data.totalLevels) : list.length;
    const total = Number.isFinite(totalRaw) && totalRaw > 0 ? totalRaw : list.length;
    const passedCount =
      data && Array.isArray(data.passedLevels)
        ? new Set(
            data.passedLevels
              .map((x) => Number(x))
              .filter((x) => Number.isFinite(x) && list.includes(x))
          ).size
        : 0;
    if (total > 0 && passedCount >= total) {
      uni.showToast({
        title: "关卡持续更新中,敬请期待,您可以前往我的关卡继续游玩~",
        icon: "none",
      });
      return;
    }
    const lv = pickMidLevelFromProgress(data);
    goPlay(lv != null && lv > 0 ? lv : 0);
  } catch {
    goPlay(0);
  }
}

function startGame() {
  const goPlay = (level) => {
    uni.navigateTo({ url: `/pages-sub/play/play?level=${level}` });
  };
  api
    .getLevelProgress({ gameTier: "beginner" })
    .then((data) => {
      const totalRaw = data && data.totalLevels != null ? Number(data.totalLevels) : NaN;
      if (!Number.isFinite(totalRaw) || totalRaw < 1) {
        uni.showToast({ title: "无法获取关卡信息，请稍后重试", icon: "none" });
        return;
      }
      const cap = totalRaw;
      const curRaw = data && data.currentLevel != null ? Number(data.currentLevel) : NaN;
      if (!Number.isFinite(curRaw) || curRaw < 1) {
        goPlay(1);
        return;
      }
      if (curRaw >= cap) {
        uni.showToast({ title: "恭喜通关全部梗图填词！", icon: "none", duration: 2000 });
        return;
      }
      goPlay(curRaw);
    })
    .catch(() => goPlay(getCurrentLevel()));
}
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

/* 浅粉蓝底 + 薄荷绿主色（背景层与 page-theme 一致） */
.page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  overflow: hidden;
  padding: 0 40rpx;
  box-sizing: border-box;
  @include pt-page-background;
}

/* 与 forum 顶栏同构：条内左侧区域对齐「返回/首页」nav-btn 位置 */

.hero {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 4vh 0 9vh;
  perspective: 800rpx;
}

/* ===== Z轴冲击：多层光环从屏幕冲出 ===== */
.hero-aura {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  z-index: 0;
  pointer-events: none;
  transform-style: preserve-3d;
}

.hero-aura-ring {
  position: absolute;
  width: 320rpx;
  height: 320rpx;
  border-radius: 50%;
  border: 3rpx solid rgba(145, 213, 139, 0.35);
  transform: translate(-50%, -50%) translateZ(0);
  animation: aura-burst 2.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) infinite;
}

.hero-aura-ring--1 {
  animation-delay: 0s;
  border-color: rgba(145, 213, 139, 0.5);
  border-width: 4rpx;
}

.hero-aura-ring--2 {
  animation-delay: 0.65s;
  border-color: rgba(169, 201, 238, 0.4);
}

.hero-aura-ring--3 {
  animation-delay: 1.3s;
  border-color: rgba(255, 235, 200, 0.4);
}

@keyframes aura-burst {
  0% {
    width: 140rpx;
    height: 140rpx;
    opacity: 0.85;
    transform: translate(-50%, -50%) scale(0.6);
  }
  60% {
    opacity: 0.3;
  }
  100% {
    width: 500rpx;
    height: 500rpx;
    opacity: 0;
    transform: translate(-50%, -50%) scale(1.5);
  }
}

/* ===== 装饰粒子：Z轴飞入 ===== */
.hero-particles {
  position: absolute;
  inset: -80rpx;
  z-index: 0;
  pointer-events: none;
  transform-style: preserve-3d;
}

.hero-particle {
  position: absolute;
  font-size: 28rpx;
  animation: particle-z-fly 3s ease-out infinite;
}

.hero-particle--1 {
  top: 5%;
  left: 18%;
  animation-delay: 0s;
  color: rgba(255, 200, 145, 0.6);
  font-size: 36rpx;
}

.hero-particle--2 {
  top: 15%;
  right: 8%;
  animation-delay: 0.5s;
  color: rgba(145, 213, 139, 0.55);
  font-size: 28rpx;
}

.hero-particle--3 {
  bottom: 20%;
  left: 6%;
  animation-delay: 1s;
  color: rgba(169, 201, 238, 0.55);
  font-size: 32rpx;
}

.hero-particle--4 {
  bottom: 12%;
  right: 16%;
  animation-delay: 1.5s;
  color: rgba(255, 200, 145, 0.55);
  font-size: 26rpx;
}

.hero-particle--5 {
  top: 38%;
  left: 2%;
  animation-delay: 2s;
  color: rgba(145, 213, 139, 0.4);
}

.hero-particle--6 {
  top: 28%;
  right: 3%;
  animation-delay: 2.5s;
  color: rgba(169, 201, 238, 0.45);
}

@keyframes particle-z-fly {
  0% {
    transform: scale(0) translateZ(-100rpx);
    opacity: 0;
  }
  30% {
    transform: scale(1.4) translateZ(0);
    opacity: 0.7;
  }
  70% {
    transform: scale(1) translateZ(0);
    opacity: 0.5;
  }
  100% {
    transform: scale(0.5) translateZ(-80rpx);
    opacity: 0;
  }
}

/* ===== 徽章：Z轴呼吸脉冲（冲向屏幕） ===== */
.hero-badge {
  position: relative;
  z-index: 1;
  width: 200rpx;
  height: 200rpx;
  border-radius: 36rpx;
  background: rgba(255, 255, 255, 0.55);
  border: 6rpx solid rgba(255, 255, 255, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 24rpx auto 30rpx;
  transform: rotate(-4deg) translateZ(0);
  overflow: hidden;
  box-sizing: border-box;
  animation: badge-z-pulse 2.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) infinite;
}

@keyframes badge-z-pulse {
  0%,
  100% {
    transform: rotate(-4deg) scale(1) translateZ(0);
    box-shadow:
      0 8rpx 24rpx rgba(169, 201, 238, 0.3),
      0 0 0 8rpx rgba(145, 213, 139, 0.06),
      0 0 0 20rpx rgba(145, 213, 139, 0.03),
      0 4rpx 0 rgba(255, 255, 255, 0.8) inset;
    filter: brightness(1);
  }
  45% {
    transform: rotate(-3deg) scale(1.1) translateZ(40rpx);
    box-shadow:
      0 24rpx 56rpx rgba(169, 201, 238, 0.5),
      0 0 0 20rpx rgba(145, 213, 139, 0.14),
      0 0 0 44rpx rgba(145, 213, 139, 0.07),
      0 4rpx 0 rgba(255, 255, 255, 0.85) inset;
    filter: brightness(1.08);
  }
}

.hero-badge-img {
  width: 100%;
  height: 100%;
}

/* ===== 标题：Z轴弹出 + 流光 ===== */
.title {
  position: relative;
  z-index: 1;
  font-size: 56rpx;
  font-weight: 900;
  letter-spacing: 0.08em;
  margin-bottom: 12rpx;
  background: linear-gradient(
    110deg,
    #5a9e52 0%,
    #6fb868 20%,
    #91d58b 34%,
    #b8e8a8 44%,
    #e8f8e4 48%,
    #b8e8a8 52%,
    #91d58b 62%,
    #6fb868 80%,
    #5a9e52 100%
  );
  background-size: 280% 100%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: title-z-pop 2.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) infinite,
    title-shimmer 2.2s ease-in-out infinite alternate;
  filter: drop-shadow(0 3rpx 0 rgba(111, 184, 104, 0.35))
    drop-shadow(0 6rpx 18rpx rgba(145, 213, 139, 0.2));
}

@keyframes title-z-pop {
  0%,
  100% {
    transform: scale(1) translateZ(0);
  }
  40% {
    transform: scale(1.06) translateZ(20rpx);
  }
}

@keyframes title-shimmer {
  0% {
    background-position: 0% 50%;
  }
  100% {
    background-position: 100% 50%;
  }
}

.subtitle {
  position: relative;
  z-index: 1;
  font-size: 24rpx;
  color: #3a86da;
  letter-spacing: 0.06em;
  background: rgba(255, 255, 255, 0.72);
  padding: 10rpx 26rpx;
  border-radius: 100rpx;
  border: 2rpx solid rgba(169, 201, 238, 0.45);
  animation: subtitle-z-pop-in 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

@keyframes subtitle-z-pop-in {
  0% {
    opacity: 0;
    transform: scale(0.5) translateZ(-60rpx);
  }
  100% {
    opacity: 1;
    transform: scale(1) translateZ(0);
  }
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
  // font-weight: 700;
  letter-spacing: 0.04em;
  transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  overflow: hidden;
  box-sizing: border-box;

  background: linear-gradient(
    180deg,
    rgb(255, 216, 229) 0%,
    rgb(245, 175, 200) 55%,
    rgb(231, 139, 174) 100%
  );
  color: #b83b5e;
  border: 2rpx solid rgba(255, 255, 255, 0.64);
  box-shadow: 0 20rpx 30rpx rgba(245, 137, 163, 0.36), 0 12rpx 0 rgba(189, 90, 126, 0.26),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.2), inset 0 -10rpx 14rpx rgba(0, 0, 0, 0.06);
  &::before {
    content: "";
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
    content: "";
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
    background: linear-gradient(180deg, #8bd4ff 0%, #56b4f8 55%, #2f8fe5 100%);
    color: #fff;
    border: 2rpx solid rgba(255, 255, 255, 0.64);
    box-shadow: 0 20rpx 30rpx rgba(79, 167, 245, 0.42), 0 12rpx 0 rgba(34, 108, 186, 0.3),
      inset 0 2rpx 0 rgba(255, 255, 255, 0.2), inset 0 -10rpx 14rpx rgba(0, 0, 0, 0.06);
  }

  &.btn-start-xhs {
    /* 主按钮：薄荷绿实底 + 白字 */
    background: linear-gradient(180deg, #ffe49a 0%, #ffc857 55%, #f4a62a 100%);
    color: #fff;
    border: 2rpx solid rgba(255, 255, 255, 0.68);
    box-shadow: 0 22rpx 32rpx rgba(255, 190, 74, 0.42), 0 12rpx 0 rgba(184, 118, 18, 0.34),
      inset 0 2rpx 0 rgba(255, 255, 255, 0.4), inset 0 -10rpx 14rpx rgba(0, 0, 0, 0.16);

    .btn-icon-img {
      width: 40rpx;
      height: 40rpx;
    }

    .btn-text {
      text-shadow: 0 2rpx 8rpx rgba(120, 78, 12, 0.32);
      // color: #ff2442;
    }
  }
}

.btn-start-main {
  width: 100%;
  padding: 36rpx 40rpx;
  font-size: 46rpx;
  flex-direction: column;
  line-height: 1.2;
  .btn-start-text1 {
    // 字间距
    letter-spacing: 0.4em;
    font-weight: bold;
    color: #d11348;
  }

  .btn-start-text2 {
    font-size: 22rpx;
    display: block;
  }
}
.start-sub-row .btn-start {
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 12rpx;
  padding: 34rpx 18rpx;
  font-size: 26rpx;
}
.btn-start--hover {
  transform: translateY(10rpx) scale(0.985);
  filter: brightness(0.97);
  box-shadow: 0 4rpx 8rpx rgba(0, 0, 0, 0.2), 0 1rpx 0 rgba(0, 0, 0, 0.12),
    inset 0 3rpx 0 rgba(255, 255, 255, 0.2), inset 0 -8rpx 12rpx rgba(0, 0, 0, 0.2);
}
.btn-start-icon {
  width: 44rpx;
  height: 44rpx;
  flex-shrink: 0;
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
  padding: 16rpx 18rpx;
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(10px);
  border-radius: 24rpx;
  color: #3a86da;
  font-size: 26rpx;
  // font-weight: 700;
  box-shadow: 0 18rpx 24rpx rgba(169, 201, 238, 0.26), 0 10rpx 0 rgba(117, 154, 194, 0.28),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.5), inset 0 -8rpx 12rpx rgba(54, 84, 120, 0.12);
  border: 2rpx solid rgba(169, 201, 238, 0.62);
  transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
  overflow: hidden;
  position: relative;
  font-weight: none;

  &.btn-entry--battle {
    .btn-text {
      font-size: 26rpx;
    }
  }
}
.btn-entry::before {
  content: "";
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
  content: "";
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
.btn-entry--hover {
  transform: translateY(8rpx) scale(0.985);
  filter: brightness(0.97);
  box-shadow: 0 4rpx 8rpx rgba(84, 116, 154, 0.24), 0 1rpx 0 rgba(84, 116, 154, 0.16),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.24), inset 0 -7rpx 10rpx rgba(54, 84, 120, 0.2);
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
  font-size: 28rpx;
  line-height: 1.2;
}

.battle-badge {
  position: absolute;
  top: -2rpx;
  right: -2rpx;
  background: linear-gradient(135deg, #ff7043, #ff5252);
  color: #fff;
  font-size: 18rpx;
  font-weight: 800;
  padding: 6rpx 14rpx;
  border-radius: 25rpx;
  border-bottom-right-radius: 0;
  border-top-left-radius: 0;
  letter-spacing: 0.04em;
  box-shadow: 0 4rpx 10rpx rgba(255, 80, 80, 0.35);
  animation: battleBadgePulse 1.8s ease-in-out infinite;
}

@keyframes battleBadgePulse {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.08);
  }
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
  color: #3a86da;
  letter-spacing: 0.03em;
  background: rgba(255, 255, 255, 0.65);
  padding: 10rpx 26rpx;
  border-radius: 100rpx;
  border: 2rpx solid rgba(169, 201, 238, 0.4);
}

.side-toolbar {
  position: fixed;
  right: 0rpx;
  bottom: 15%;
  z-index: 50;
  display: flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255, 255, 255, 0.82);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 25rpx;
  border: 3rpx solid rgba(169, 201, 238, 0.45);
  box-shadow: 0 8rpx 24rpx rgba(169, 201, 238, 0.2);
  padding: 20rpx 0;
}

.daily-task-float {
  position: fixed;
  right: 0rpx;
  top: 38%;
  transform: translateY(-50%);
  z-index: 52;
  width: 112rpx;
  min-height: 120rpx;
  padding: 16rpx 10rpx;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6rpx;
  border-radius: 22rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.72);
  border-right: none;
  background: linear-gradient(180deg, #ffd98f 0%, #f7be60 100%);
  box-shadow: 0 10rpx 24rpx rgba(242, 180, 95, 0.35);
  animation: daily-task-float-wiggle 2.4s ease-in-out infinite;
}

/* 与 wiggle 动画共用 transform，按下态仅用透明度，避免小程序 :active 粘连 */
.daily-task-float--hover {
  opacity: 0.92;
}

.streamer-float {
  position: fixed;
  right: 0;
  top: 30%;
  transform: translateY(-50%);
  z-index: 52;
  width: 120rpx;
  padding: 0;
  box-sizing: border-box;
  border-radius: 24rpx 0 0 24rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.85);
  border-right: none;
  background: linear-gradient(160deg, #c084fc 0%, #a855f7 55%, #9333ea 100%);
  box-shadow: 0 8rpx 28rpx rgba(168, 85, 247, 0.5),
    inset 0 2rpx 0 rgba(255, 255, 255, 0.35);
  overflow: hidden;
  // animation: streamer-float-wiggle 3s ease-in-out infinite;
}

.streamer-float::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 50%;
  background: linear-gradient(to bottom, rgba(255, 255, 255, 0.28) 0%, transparent 100%);
  pointer-events: none;
  border-radius: 24rpx 0 0 0;
}

.streamer-float--hover {
  opacity: 0.9;
  filter: brightness(0.96);
}

.streamer-float-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4rpx;
  padding: 18rpx 10rpx;
}

.streamer-float-icon {
  font-size: 36rpx;
  line-height: 1;
  animation: streamer-icon-bounce 1.6s ease-in-out infinite;
}

.streamer-float-title {
  font-size: 24rpx;
  font-weight: 900;
  color: #fff;
  letter-spacing: 0.06em;
  line-height: 1.3;
  text-shadow: 0 1rpx 4rpx rgba(150, 40, 0, 0.4);
}

.streamer-float-sub {
  font-size: 24rpx;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.04em;
  line-height: 1.2;
}

.streamer-float-dot {
  width: 16rpx;
  height: 16rpx;
  border-radius: 50%;
  background: #fff;
  margin: 0 auto 14rpx;
  opacity: 0.9;
  animation: streamer-dot-pulse 1.4s ease-in-out infinite;
}

@keyframes streamer-float-wiggle {
  0%,
  100% {
    transform: translateY(-50%) rotate(0deg);
  }
  25% {
    transform: translateY(-52%) rotate(2deg);
  }
  75% {
    transform: translateY(-48%) rotate(-2deg);
  }
}

@keyframes streamer-icon-bounce {
  0%,
  100% {
    transform: translateY(0) scale(1);
  }
  50% {
    transform: translateY(-4rpx) scale(1.12);
  }
}

@keyframes streamer-dot-pulse {
  0%,
  100% {
    opacity: 0.9;
    transform: scale(1);
  }
  50% {
    opacity: 0.4;
    transform: scale(0.7);
  }
}

.daily-task-float-icon {
  font-size: 30rpx;
  line-height: 1;
}

.daily-task-float-text {
  font-size: 20rpx;
  line-height: 1.25;
  font-weight: 800;
  color: #754400;
  letter-spacing: 0.04em;
}

@keyframes daily-task-float-wiggle {
  0%,
  100% {
    transform: translateY(-50%) rotate(0deg);
  }
  25% {
    transform: translateY(-50%) rotate(-4deg);
  }
  75% {
    transform: translateY(-50%) rotate(4deg);
  }
}

.toolbar-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100rpx;
  height: 90rpx;
  gap: 4rpx;
  transition: all 0.15s ease;
}

.toolbar-item--hover {
  opacity: 0.82;
  filter: brightness(0.96);
}

.toolbar-item--tasks-highlight {
  position: relative;
  border-radius: 12rpx;
  background: linear-gradient(
    180deg,
    rgba(255, 246, 221, 0.95),
    rgba(255, 236, 185, 0.88)
  );
  box-shadow: 0 10rpx 24rpx rgba(245, 185, 47, 0.26);
  animation: tasksPulse 1.9s ease-in-out infinite;
}

.toolbar-item--tasks-highlight .btn-start-icon {
  animation: tasksIconBounce 1.5s ease-in-out infinite;
}

.toolbar-tasks-badge {
  position: absolute;
  top: 2rpx;
  right: 0rpx;
  min-width: 34rpx;
  height: 22rpx;
  border-radius: 999rpx;
  background: linear-gradient(135deg, #ff5f6d, #ff8a00);
  color: #fff;
  font-size: 16rpx;
  font-weight: 800;
  line-height: 22rpx;
  text-align: center;
  letter-spacing: 0.03em;
  box-shadow: 0 6rpx 14rpx rgba(255, 95, 109, 0.35);
  animation: tasksBadgePop 1.3s ease-in-out infinite;
}

@keyframes tasksPulse {
  0%,
  100% {
    transform: translateY(0);
    box-shadow: 0 10rpx 24rpx rgba(245, 185, 47, 0.2);
  }
  50% {
    transform: translateY(-2rpx);
    box-shadow: 0 14rpx 30rpx rgba(245, 185, 47, 0.32);
  }
}

@keyframes tasksIconBounce {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-3rpx);
  }
}

@keyframes tasksBadgePop {
  0%,
  100% {
    transform: scale(1);
    opacity: 0.9;
  }
  50% {
    transform: scale(1.12);
    opacity: 1;
  }
}

.toolbar-item--asset-highlight {
  position: relative;
  border-radius: 6rpx;
  background: rgba(255, 255, 255, 0.55);
  box-shadow: 0 0 0 0 rgba(245, 196, 73, 0.35);
  animation: assetPulse 2.1s ease-in-out infinite;
}

.toolbar-item--asset-highlight .toolbar-icon {
  animation: assetIconBob 1.8s ease-in-out infinite;
}

@keyframes assetPulse {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(245, 196, 73, 0.35);
    transform: translateY(0);
  }
  50% {
    box-shadow: 0 0 24rpx 6rpx rgba(245, 196, 73, 0.28);
    transform: translateY(-1rpx);
  }
}

@keyframes assetIconBob {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-3rpx);
  }
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
  margin-bottom: 4rpx;
}

.toolbar-text {
  font-size: 22rpx;
  color: #3a86da;
  line-height: 1;
  white-space: nowrap;
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
  color: #3a86da;
}
.changelog-suppress-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
  margin-bottom: 24rpx;
  padding: 0 4rpx;
}
.changelog-suppress-check {
  width: 36rpx;
  height: 36rpx;
  border-radius: 8rpx;
  border: 2rpx solid rgba(169, 201, 238, 0.8);
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s;
}
.changelog-suppress-check--on {
  background: #91d58b;
  border-color: #6fb868;
}
.changelog-suppress-tick {
  font-size: 24rpx;
  color: #fff;
  font-weight: 800;
  line-height: 1;
}
.changelog-suppress-label {
  font-size: 24rpx;
  color: #3a86da;
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
.changelog-btn--hover {
  opacity: 0.92;
  transform: scale(0.98);
}
.changelog-btn-text {
  font-size: 28rpx;
  font-weight: 700;
  color: #fff;
}
</style>
