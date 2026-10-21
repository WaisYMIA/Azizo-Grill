# Testing Azizo on Your Phone (Offline / Before cPanel Deployment)

This is a special copy of the Azizo website + backend, pre-configured to run
**entirely on your Android phone**, with no hosting, no domain, and no
internet required once it's installed. It's for testing only — when you're
ready to go live, use `azizo-complete-project.zip` and the main deployment
guide instead.

**This only works on Android.** iPhones can't run a Node.js server locally
without extra apps outside the App Store, so there's no equivalent iPhone
version — if you only have an iPhone, ask someone with an Android phone to
help with this step, or just wait for the real cPanel deployment.

## What you'll be able to test

- The full website (menu, cart, WhatsApp checkout) exactly as a customer sees it
- Logging into `admin.html` and uploading real product photos
- Downloading/uploading the Excel product catalog
- Orders actually saving to a real database on your phone

## Step 1 — Install Termux

Termux turns part of your phone into a small computer that can run Node.js.

1. Install **Termux** from F-Droid (recommended): https://f-droid.org/packages/com.termux/
   (The Play Store version is outdated and often broken — use F-Droid.)
2. Open Termux once it's installed. You'll see a black terminal screen.

## Step 2 — Set up Node.js inside Termux

Type each line below into Termux and press Enter after each one. This downloads
Node.js and gives Termux access to your phone's normal storage (like Downloads).

```
pkg update -y
pkg install -y nodejs
termux-setup-storage
```

The last command will pop up a permission request — tap **Allow**.

## Step 3 — Get this folder onto your phone

1. Download `azizo-phone-test.zip` (the file I sent) to your phone — it'll land
   in your **Downloads** folder.
2. Back in Termux, run:
   ```
   cd ~/storage/downloads
   unzip azizo-phone-test.zip -d azizo-test
   cd azizo-test/backend
   ```
   (If `unzip` says it's not found, run `pkg install -y unzip` first, then repeat.)

## Step 4 — Install and start it

Still inside `azizo-test/backend` in Termux:

```
npm install
node server.js
```

The first `npm install` takes a few minutes on a phone — that's normal. When
it's done, you should see:

```
Azizo backend running on port 3000
```

Leave Termux open and running in the background (don't swipe it away).

## Step 5 — Open the site

Open Chrome (or any browser) on the same phone and go to:

```
http://localhost:3000
```

That's the real website, running from your phone, to your phone. No WiFi
or data connection is needed once `npm install` has finished.

## Logging into the admin panel

Open `http://localhost:3000/admin.html`, and when it asks for the backend
URL, use:

```
http://localhost:3000
```

Admin password: `majani90` (same as everywhere else right now — change this
before you ever go live for real).

## Stopping / restarting

- To stop the server: tap back into Termux and press `Ctrl` then `C` (or type
  `Ctrl+C` using Termux's extra-keys row).
- To start it again later: open Termux and run:
  ```
  cd ~/storage/downloads/azizo-test/backend
  node server.js
  ```
  You won't need to run `npm install` again unless you re-unzip a fresh copy.

## Important notes

- Anything you add through the admin panel here (products, photos, orders)
  is saved in a database file **on your phone only** — it does NOT appear on
  your real website and does NOT carry over to cPanel automatically. This is
  purely a sandbox for trying things out.
- This copy has `API_BASE_URL` pre-set to `http://localhost:3000`, which only
  makes sense on this phone. Don't deploy this exact copy to cPanel — use
  `azizo-complete-project.zip` for that instead (it's set up correctly for
  real deployment).
- If Chrome shows "can't connect", it usually means the Termux server stopped
  or the phone went to sleep and paused Termux — reopen Termux and check it's
  still showing "Azizo backend running on port 3000".


## What to try in v1.8
1. Place an order, then reload: name/phone/address are pre-filled and "Your usual" chips appear.
2. Tick a standing-order frequency at checkout; open Staff > Standing and press "Create this order".
3. Staff: use "Tell customer" on an order; mark an item out for one branch in Stock and see the checkout warning.
4. Complete an order, track it, leave a review with a photo; approve it in Admin > Reviews and see it on Reviews.
5. Open rider.html for the delivery run sheet.
