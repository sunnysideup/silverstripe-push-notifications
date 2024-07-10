let deferredPrompt
window.addEventListener('beforeinstallprompt', e => {
  deferredPrompt = e

  const installAppButton = document.getElementById('add-to-home-screen')
  if (installAppButton) {
    installAppButton.removeAttribute('disabled')
  }
})

const installAppButton = document.getElementById('add-to-home-screen')
if (installAppButton) {
  installAppButton.setAttribute('disabled', 'disabled')
  installAppButton.addEventListener('click', async () => {
    if (typeof deferredPrompt !== 'undefined') {
      deferredPrompt.prompt()
      const { outcome } = await deferredPrompt.userChoice
      if (outcome === 'accepted') {
        deferredPrompt = null
      }
    }
  })
}
