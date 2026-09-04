import { getSdkClient } from '../app/sdk-client.js';

function clientOrThrow() {
  const client = getSdkClient();
  if (!client?.getToken()) throw new Error('请先登录后再管理内容。');
  return client;
}

export async function deleteCard(id) {
  return clientOrThrow().cards.delete(Number(id));
}

export async function deleteComment(id) {
  return clientOrThrow().comments.delete(Number(id));
}

export async function loadLikes() {
  const likes = await clientOrThrow().likes.list();
  return Array.isArray(likes) ? likes : [];
}

export async function updatePassword({ current_password, new_password } = {}) {
  if (!current_password?.trim() || !new_password?.trim()) throw new Error('请输入当前密码和新密码。');
  return clientOrThrow().users.updatePassword({ current_password: current_password.trim(), new_password: new_password.trim() });
}

export async function updateEmail({ current_password, email, captcha } = {}) {
  if (!current_password?.trim() || !email?.trim() || !captcha?.trim()) throw new Error('请输入当前密码、邮箱和验证码。');
  return clientOrThrow().users.updateEmail({ current_password: current_password.trim(), email: email.trim(), captcha: captcha.trim() });
}

export async function sendEmailCaptcha(email) {
  if (!email?.trim()) throw new Error('请输入邮箱。');
  return clientOrThrow().users.emailCaptcha({ email: email.trim() });
}
