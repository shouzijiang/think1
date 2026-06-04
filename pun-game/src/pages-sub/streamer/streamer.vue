<template>
  <view class="page">
    <view class="bg-wrap">
      <view class="bg-gradient" />
      <view class="bg-dots" />
    </view>

    <PunPageNavBar
      :status-bar-height="statusBarHeight"
      :nav-bar-height="navBarHeight"
      :menu-button-height="menuButtonHeight"
      title="邀请好友赚收益"
      @left-click="back"
    />

    <!-- 邀请码区域 -->
    <view class="qr-card">
      <text class="qr-title">📡 你的专属邀请码,你的ID：{{ userId || "加载中…" }}</text>
      <text class="qr-desc">扫码或者分享链接进入的好友即自动绑定，获取收益</text>

      <view class="qr-wrap">
        <view v-if="qrLoading" class="qr-placeholder">
          <text class="qr-loading-text">生成中…</text>
        </view>
        <image
          v-else-if="qrTempPath"
          class="qr-img"
          :src="qrTempPath"
          mode="aspectFit"
          show-menu-by-longpress
        />
        <view v-else class="qr-placeholder qr-placeholder--error">
          <text class="qr-loading-text">请生成您的专属邀请码。</text>
        </view>
      </view>

      <view class="qr-actions">
        <button class="qr-btn" :disabled="qrLoading" @click="loadQrCode">
          {{ qrLoading ? "生成中…" : hasQr ? "刷新二维码" : "生成专属二维码" }}
        </button>
        <button v-if="hasQr" class="qr-btn qr-btn--save" @click="saveQrCode">
          保存到相册
        </button>
      </view>
      <view class="qr-share-row">
        <button class="qr-btn qr-btn--share" open-type="share">📤 立刻邀请好友</button>
      </view>
    </view>

    <!-- 数据统计 -->
    <view class="stats-card">
      <view class="stats-header">
        <text class="stats-title">📊 邀请数据</text>
        <text class="stats-refresh" @click="loadStats">刷新</text>
      </view>

      <view v-if="statsLoading" class="stats-loading">
        <text>加载中…</text>
      </view>
      <template v-else>
        <view class="stats-row">
          <view class="stat-item">
            <text class="stat-num">{{ stats.totalUsers }}</text>
            <text class="stat-label">累计受邀</text>
          </view>
          <view class="stat-divider" />
          <view class="stat-item">
            <text class="stat-num stat-num--green">{{ stats.loginCount }}</text>
            <text class="stat-label">累计登录次数</text>
          </view>
          <view class="stat-divider" />
          <view class="stat-item">
            <text class="stat-num stat-num--blue">{{ stats.videoCount }}</text>
            <text class="stat-label">累计激励视频</text>
          </view>
        </view>
        <view class="stats-row">
          <view class="stat-item">
            <text class="stat-num stat-num--gold">{{
              formatMoneyDisplay(stats.totalPaid)
            }}</text>
            <text class="stat-label">已结算金额(元)</text>
          </view>
          <view class="stat-divider" />
          <view class="stat-item">
            <text class="stat-num stat-num--orange">{{
              formatMoneyDisplay(stats.unsettledGross)
            }}</text>
            <text class="stat-label">待结算(元)</text>
          </view>
          <view class="stat-divider" />
          <view class="stat-item">
            <text class="stat-num stat-num--blue">{{
              formatMoneyDisplay(stats.balance)
            }}</text>
            <text class="stat-label">可提现余额(元)</text>
          </view>
        </view>
        <view class="stats-earn-tip">
          <text v-if="stats.lastSettledDate"
            >已结算至 {{ formatSettledDate(stats.lastSettledDate) }}</text
          >
          <text v-else>尚未结算，待结算按全平台单价累计</text>
          <text> · 满 {{ stats.withdrawMin }} 元可提现</text>
        </view>
      </template>
    </view>

    <!-- 加入群聊 -->
    <view class="group-card">
      <text class="group-title">💬 加入玩家交流群</text>
      <text class="group-desc">扫码进群，第一时间获取活动福利</text>
      <view class="group-qr-wrap">
        <image
          class="group-qr-img"
          src="https://sofun.online/static/group_qr.jpg"
          mode="aspectFit"
          show-menu-by-longpress
        />
      </view>
      <text class="group-tip">长按二维码识别加入</text>
    </view>
  </view>
</template>

<script setup>
import { ref, computed } from "vue";
import { onShow, onShareAppMessage } from "@dcloudio/uni-app";
import { api } from "../../utils/api";
import { useNavBar } from "../../composables/useNavBar";
import { getUserInfo } from "../../utils/auth";
import PunPageNavBar from "../../components/PunPageNavBar.vue";

const { statusBarHeight, navBarHeight, menuButtonHeight } = useNavBar();

const QR_STORAGE_KEY = "streamer_qr_base64";

const hasQr = ref(false);
const qrTempPath = ref(""); // 本地临时文件路径，用于长按保存
const qrLoading = ref(false);
const channel = ref("");
const userId = ref("");
let qrBase64Cache = "";

// base64 → 临时文件，供长按保存使用
function writeQrTempFile(base64) {
  // #ifdef MP-WEIXIN
  try {
    const fs = wx.getFileSystemManager();
    const filePath = `${wx.env.USER_DATA_PATH}/streamer_qr_preview.png`;
    fs.writeFileSync(filePath, base64, "base64");
    qrTempPath.value = filePath;
  } catch (e) {
    qrTempPath.value = "";
  }
  // #endif
}

// 从本地缓存读取 userId
function loadUserId() {
  const info = getUserInfo();
  userId.value = info?.user_id ? String(info.user_id) : "";
}

// 从 storage 恢复二维码（优先内存缓存，避免同步读盘）
function restoreQrFromStorage() {
  // 优先从内存恢复（loadQrCode 回写时会同时更新缓存与 storage）
  if (qrBase64Cache) {
    hasQr.value = true;
    writeQrTempFile(qrBase64Cache);
    return;
  }
  try {
    uni.getStorage({
      key: QR_STORAGE_KEY,
      success: (res) => {
        const saved = res.data;
        if (saved) {
          qrBase64Cache = String(saved);
          hasQr.value = true;
          writeQrTempFile(saved);
        }
      },
      fail() {},
    });
  } catch (e) {}
}

const stats = ref({
  totalUsers: 0,
  loginCount: 0,
  videoCount: 0,
  totalPaid: "0.000",
  unsettledGross: "0.000",
  balance: "0.000",
  lastSettledDate: null,
  withdrawMin: "1",
  canWithdraw: false,
});
const statsLoading = ref(false);

/** 金额展示：截断 3 位小数，不四舍五入（与后端一致） */
function formatMoneyYuan(value) {
  const raw = String(value ?? "").trim();
  if (raw === "" || raw === "null" || raw === "undefined") return "0.000";
  const negative = raw.startsWith("-");
  const num = negative ? raw.slice(1) : raw;
  if (!/^\d+(\.\d+)?$/.test(num)) return "0.000";
  const [intPart, decPart = ""] = num.split(".");
  const dec = (decPart + "000").slice(0, 3);
  return `${negative ? "-" : ""}${intPart}.${dec}`;
}

/** 页面展示：金额为 0 时显示 —，避免未结算时满屏 0.000 */
function formatMoneyDisplay(value) {
  const formatted = formatMoneyYuan(value);
  return formatted === "0.000" ? "—" : formatted;
}

/** 结算日期：小程序模板里 YYYY-MM-DD 会被当成减法，改用中文日期 */
function formatSettledDate(value) {
  const raw = String(value ?? "")
    .trim()
    .slice(0, 10);
  const matched = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);
  if (!matched) return raw;
  const [, year, month, day] = matched;
  return `${year}年${Number(month)}月${Number(day)}日`;
}

async function loadQrCode() {
  if (qrLoading.value) return;
  qrLoading.value = true;
  try {
    const data = await api.getStreamerQrCode();
    const nextBase64 = data.qrBase64 || "";
    qrBase64Cache = String(nextBase64);
    hasQr.value = !!nextBase64;
    channel.value = data.channel || "";
    if (nextBase64) {
      try {
        uni.setStorage({ key: QR_STORAGE_KEY, data: nextBase64, fail() {} });
      } catch (e) {}
      writeQrTempFile(nextBase64);
    } else {
      qrTempPath.value = "";
    }
  } catch (e) {
    uni.showToast({ title: e?.message || "生成失败", icon: "none" });
  } finally {
    qrLoading.value = false;
  }
}

async function loadStats() {
  statsLoading.value = true;
  try {
    const data = await api.getStreamerStats();
    stats.value = {
      totalUsers: Number(data?.totalUsers || 0),
      loginCount: Number(data?.loginCount || 0),
      videoCount: Number(data?.videoCount || 0),
      totalPaid: String(data?.totalPaid ?? "0.000"),
      unsettledGross: String(data?.unsettledGross ?? "0.000"),
      balance: String(data?.balance ?? "0.000"),
      lastSettledDate: data?.lastSettledDate
        ? String(data.lastSettledDate).slice(0, 10)
        : null,
      withdrawMin: String(data?.withdrawMin ?? "1"),
      canWithdraw: !!data?.canWithdraw,
    };
    if (!channel.value && data.channel) channel.value = data.channel;
  } catch (e) {
    uni.showToast({ title: "数据加载失败", icon: "none" });
  } finally {
    statsLoading.value = false;
  }
}

function saveQrCode() {
  if (!qrBase64Cache) return;
  // #ifdef MP-WEIXIN
  const doSave = () => {
    const fs = wx.getFileSystemManager();
    const filePath = `${wx.env.USER_DATA_PATH}/streamer_qr_${Date.now()}.png`;
    fs.writeFile({
      filePath,
      data: qrBase64Cache,
      encoding: "base64",
      success: () => {
        wx.saveImageToPhotosAlbum({
          filePath,
          success: () => uni.showToast({ title: "已保存到相册", icon: "success" }),
          fail: () => {
            wx.showModal({
              title: "提示",
              content: "请长按二维码保存，或者在设置中开启相册权限后重试",
              confirmText: "去设置",
              success: (res) => {
                if (res.confirm) wx.openSetting();
              },
            });
          },
        });
      },
      fail: () => uni.showToast({ title: "文件写入失败", icon: "none" }),
    });
  };

  const albumDeniedModal = () => {
    wx.showModal({
      title: "提示",
      content: "请长按二维码保存，或在设置中开启「保存到相册」权限后重试",
      confirmText: "去设置",
      success: (r) => {
        if (!r.confirm) return;
        wx.openSetting({
          success: (openRes) => {
            if (openRes.authSetting["scope.writePhotosAlbum"]) doSave();
          },
        });
      },
    });
  };

  wx.getSetting({
    success: (res) => {
      const album = res.authSetting["scope.writePhotosAlbum"];
      if (album === true) {
        doSave();
        return;
      }
      // 用户曾拒绝：authorize 不会再弹系统授权框，只能去设置里打开
      if (album === false) {
        albumDeniedModal();
        return;
      }
      wx.authorize({
        scope: "scope.writePhotosAlbum",
        success: doSave,
        fail: albumDeniedModal,
      });
    },
    fail: doSave, // getSetting 失败时直接尝试保存
  });
  // #endif
  // #ifndef MP-WEIXIN
  uni.showToast({ title: "请截图保存", icon: "none" });
  // #endif
}

function formatTime(str) {
  if (!str) return "";
  return str.slice(0, 16).replace("T", " ");
}

function back() {
  uni.navigateBack({ delta: 1, fail: () => uni.reLaunch({ url: "/pages/index/index" }) });
}

onShareAppMessage(() => {
  const shareChannel = channel.value || (userId.value ? `streamer_${userId.value}` : "");
  return {
    title: `邀请你来玩谐音梗猜一猜，用我的专属链接进入`,
    path: shareChannel
      ? `/pages/index/index?channel=${encodeURIComponent(shareChannel)}`
      : "/pages/index/index",
  };
});

onShow(() => {
  loadUserId();
  restoreQrFromStorage();
  loadStats();
});
</script>

<style lang="scss" scoped>
@use '../../styles/page-theme.scss' as *;

.page {
  min-height: 100vh;
  padding: 0 32rpx 60rpx;
  box-sizing: border-box;
  @include pt-page-background;
}

.qr-card,
.stats-card {
  position: relative;
  z-index: 2;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 28rpx;
  padding: 26rpx 22rpx;
  margin-bottom: 28rpx;
  box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.18);
  border: 2rpx solid rgba(169, 201, 238, 0.4);
}

.qr-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #5a6d7a;
  display: block;
  margin-bottom: 8rpx;
}
.qr-desc {
  font-size: 24rpx;
  color: #8eadcf;
  display: block;
  margin-bottom: 28rpx;
}
.qr-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 24rpx;
}
.qr-img {
  width: 320rpx;
  height: 320rpx;
  border-radius: 16rpx;
  border: 4rpx solid rgba(169, 201, 238, 0.4);
}
.qr-placeholder {
  width: 320rpx;
  height: 320rpx;
  border-radius: 16rpx;
  background: rgba(234, 246, 249, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
}
.qr-placeholder--error {
  background: rgba(255, 230, 230, 0.6);
}
.qr-loading-text {
  font-size: 26rpx;
  color: #8eadcf;
}
.qr-channel-tag {
  display: flex;
  align-items: center;
  gap: 12rpx;
  margin-bottom: 24rpx;
  background: rgba(234, 246, 249, 0.8);
  border-radius: 12rpx;
  padding: 12rpx 18rpx;
}
.qr-channel-label {
  font-size: 22rpx;
  color: #8eadcf;
}
.qr-channel-value {
  font-size: 22rpx;
  font-weight: 700;
  color: #5a6d7a;
  word-break: break-all;
}
.qr-actions {
  display: flex;
  gap: 16rpx;
}
.qr-btn {
  flex: 1;
  background: linear-gradient(135deg, #a3dd9c, #6fb868);
  color: #fff;
  font-size: 28rpx;
  font-weight: 700;
  border-radius: 48rpx;
  border: none;
  padding: 10rpx 0;
  box-shadow: 0 4rpx 14rpx rgba(111, 184, 104, 0.3);
}
.qr-btn--save {
  background: linear-gradient(135deg, #90caf9, #42a5f5);
  box-shadow: 0 4rpx 14rpx rgba(66, 165, 245, 0.3);
}
.qr-share-row {
  margin-top: 16rpx;
  .qr-btn--share {
    background: linear-gradient(135deg, #6ee7b7, #10b981);
    box-shadow: 0 4rpx 14rpx rgba(16, 185, 129, 0.3);
    color: #fff;
    padding: 15rpx 0;
  }
}

.group-card {
  position: relative;
  z-index: 2;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 28rpx;
  padding: 26rpx 22rpx;
  margin-bottom: 28rpx;
  box-shadow: 0 6rpx 20rpx rgba(169, 201, 238, 0.18);
  border: 2rpx solid rgba(169, 201, 238, 0.4);
  display: flex;
  flex-direction: column;
  align-items: center;
}
.group-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #5a6d7a;
  display: block;
  margin-bottom: 8rpx;
  align-self: flex-start;
}
.group-desc {
  font-size: 24rpx;
  color: #8eadcf;
  display: block;
  margin-bottom: 24rpx;
  align-self: flex-start;
}
.group-qr-wrap {
  width: 280rpx;
  height: 280rpx;
  border-radius: 16rpx;
  overflow: hidden;
  border: 4rpx solid rgba(169, 201, 238, 0.4);
  margin-bottom: 16rpx;
}
.group-qr-img {
  width: 100%;
  height: 100%;
}
.group-tip {
  font-size: 22rpx;
  color: #8eadcf;
}

/* 统计 */
.stats-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
}
.stats-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #5a6d7a;
}
.stats-refresh {
  font-size: 24rpx;
  color: #8eadcf;
  padding: 6rpx 18rpx;
  background: rgba(234, 246, 249, 0.9);
  border-radius: 20rpx;
}
.stats-loading {
  text-align: center;
  padding: 40rpx 0;
  color: #8eadcf;
  font-size: 26rpx;
}
.stats-row {
  display: flex;
  align-items: center;
  justify-content: space-around;
  margin-bottom: 28rpx;
  background: rgba(234, 246, 249, 0.6);
  border-radius: 18rpx;
  padding: 24rpx 0;
}
.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8rpx;
}
.stat-num {
  font-size: 48rpx;
  font-weight: 900;
  color: #5a6d7a;
  line-height: 1;
}
.stat-num--green {
  color: #6fb868;
}
.stat-num--blue {
  color: #42a5f5;
}
.stat-num--gold {
  color: #d4a017;
}
.stat-num--orange {
  color: #f59e0b;
}
.stats-earn-tip {
  font-size: 22rpx;
  color: #8eadcf;
  line-height: 1.6;
  padding: 0 8rpx 8rpx;
  text-align: center;
}
.stat-label {
  font-size: 22rpx;
  color: #8eadcf;
}
.stat-divider {
  width: 2rpx;
  height: 60rpx;
  background: rgba(169, 201, 238, 0.4);
}

.user-list-title {
  font-size: 26rpx;
  font-weight: 700;
  color: #8eadcf;
  display: block;
  margin-bottom: 16rpx;
}
.user-item {
  display: flex;
  align-items: center;
  gap: 18rpx;
  padding: 18rpx 0;
  border-bottom: 1rpx solid rgba(169, 201, 238, 0.2);
}
.user-item:last-child {
  border-bottom: none;
}
.user-avatar {
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  background: rgba(234, 246, 249, 0.95);
  border: 2rpx solid rgba(169, 201, 238, 0.4);
  flex-shrink: 0;
}
.user-avatar--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 34rpx;
}
.user-info {
  flex: 1;
}
.user-name {
  font-size: 28rpx;
  font-weight: 600;
  color: #5a6d7a;
  display: block;
}
.user-time {
  font-size: 22rpx;
  color: #8eadcf;
  display: block;
  margin-top: 4rpx;
}
.user-list-empty {
  text-align: center;
  padding: 40rpx 0;
  font-size: 26rpx;
  color: #8eadcf;
}
</style>
