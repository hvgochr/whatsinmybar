export function announceSessionChange(event: 'changed' | 'logout'): void {
  if (import.meta.client && typeof BroadcastChannel !== 'undefined') {
    const channel = new BroadcastChannel('whatsinmybar.session')
    channel.postMessage(event)
    channel.close()
  }
}
