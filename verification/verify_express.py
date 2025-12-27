from playwright.sync_api import sync_playwright

def verify_express_batch():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()

        try:
            # 1. Login
            page.goto("http://localhost:8081/express/exp/index.php?action=login")
            print("Navigating to Login Page...")

            # Check if redirected to login page or already on it
            if "login" in page.url or page.locator("input[name='username']").count() > 0:
                print("Logging in...")
                page.fill("input[name='username']", "admin")
                page.fill("input[name='password']", "password")
                page.click("button[type='submit']")
                page.wait_for_load_state('networkidle')

            # 2. Go to Batch List
            print("Navigating to Batch List...")
            page.goto("http://localhost:8081/express/exp/index.php?action=batch_list")
            page.wait_for_load_state('networkidle')

            # 3. Take Screenshot
            screenshot_path = "verification/express_batch_list.png"
            page.screenshot(path=screenshot_path, full_page=True)
            print(f"Screenshot saved to {screenshot_path}")

        except Exception as e:
            print(f"Error: {e}")
            page.screenshot(path="verification/error.png")
        finally:
            browser.close()

if __name__ == "__main__":
    verify_express_batch()
