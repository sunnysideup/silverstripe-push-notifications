let deferredPrompt

// Detects if device is on iOS
const isIos = () => {
  const userAgent = window.navigator.userAgent.toLowerCase()
  return /iphone|ipad|ipod/.test(userAgent)
}

// Detects if device is in standalone mode
const isInStandaloneMode = () =>
  'standalone' in window.navigator && window.navigator.standalone

// Function to check if the app is already installed
const isAppInstalled = () => {
  return localStorage.getItem('appInstalled') === 'true'
}

window.addEventListener('beforeinstallprompt', e => {
  deferredPrompt = e
  console.log('App can be added to home screen')

  const installAppButton = document.getElementById('add-to-home-screen')
  const alternativeInfo = document.getElementById(
    'add-to-home-screen-alternative-info'
  )

  if (installAppButton && !isAppInstalled()) {
    installAppButton.removeAttribute('disabled')
    alternativeInfo.style.display = 'none'
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
        localStorage.setItem('appInstalled', 'true')
        deferredPrompt = null
      }
    } else {
      alert('Sorry, we could not install the app.')
    }
  })
}

// Update button state on page load
document.addEventListener('DOMContentLoaded', () => {
  // Checks if should display install popup notification:
  if (isIos() && !isInStandaloneMode()) {
    this.setState({ showInstallMessage: true })
  }
  // get elements
  const installAppButton = document.getElementById('add-to-home-screen')
  const alternativeInfo = document.getElementById(
    'add-to-home-screen-alternative-info'
  )
  const appInstalledInfo = document.getElementById('added-to-home-screen-info')
  // check if button is
  if (installAppButton) {
    if (isAppInstalled()) {
      alternativeInfo.style.display = 'none'
      installAppButton.style.display = 'none'
      appInstalledInfo.style.display = 'block'
    } else {
      appInstalledInfo.style.display = 'none'
    }
  }
})
