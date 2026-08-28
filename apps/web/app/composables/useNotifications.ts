import { toast } from 'vue-sonner'

interface NotificationOptions {
  description?: string
}

export function useNotifications() {
  function success(id: string, message: string, options: NotificationOptions = {}): void {
    toast.success(message, { ...options, id })
  }

  function error(id: string, message = 'Something went wrong. Please try again.', options: NotificationOptions = {}): void {
    toast.error(message, { ...options, id })
  }

  return { error, success }
}
