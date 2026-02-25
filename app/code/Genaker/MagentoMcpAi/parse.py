import requests
from bs4 import BeautifulSoup
import time
import json
import os
import sys
import traceback
from datetime import datetime

# Base search result pages
urls = [
    f"https://california-safes.com/catalogsearch/result/index/?cat=&limit=500&p={i}&q=a"
    for i in range(1, 6)
]

headers = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
}

products = []

# Prepare the output files
output_md_file = "california_safes_full_catalog.md"
output_json_file = "california_safes_debug.json"
trace_log_file = "california_safes_trace_log.txt"

# Create/overwrite the markdown file with initial header
with open(output_md_file, "w", encoding="utf-8") as f:
    f.write("# California Safes Full Product Metadata\n\n")

# Function to append a product to the markdown file
def write_product_to_md(product):
    with open(output_md_file, "a", encoding="utf-8") as f:
        f.write(f"## {product['title']}\n")
        f.write(f"- **Price**: {product['price']}\n")
        f.write(f"- **URL**: [{product['url']}]({product['url']})\n")
        f.write(f"- **Breadcrumbs**: {product['breadcrumbs']}\n")
        f.write(f"- **Description**:\n  {product['description']}\n\n")
        
        if product['main_image']:
            f.write("### Product Image\n")
            f.write(f"![Product Image]({product['main_image']})\n\n")
        
        if product['attributes']:
            f.write("### Additional Attributes\n")
            for k, v in product['attributes'].items():
                f.write(f"- **{k}**: {v}\n")
        f.write("\n---\n\n")
    
    print(f"✓ Product '{product['title']}' written to {output_md_file}")

# Function to update the JSON file with all products so far
def update_json_file():
    with open(output_json_file, "w", encoding="utf-8") as f:
        json.dump(products, f, indent=2)
    print(f"✓ Updated JSON data in {output_json_file}")

# Step 1: Collect product URLs from search result pages
product_urls = []

for url in urls:
    print(f"Collecting products from: {url}")
    r = requests.get(url, headers=headers)
    soup = BeautifulSoup(r.text, 'html.parser')
    
    # The product items are in li elements with class "item"
    product_items = soup.select('ul.products-grid li.item')
    
    print(f"Found {len(product_items)} items on page")
    
    for item in product_items:
        # Get product URL from the product name link
        product_link = item.select_one('.product-name a')
        if product_link and 'href' in product_link.attrs:
            product_urls.append(product_link['href'])

print(f"Found {len(product_urls)} product URLs")
print(f"Sample URLs: {product_urls[:3]}")

# Step 2: Visit each product page to extract full data
for i, link in enumerate(product_urls):
    print(f"\n==== Parsing product {i+1}/{len(product_urls)}: {link} ====")
    try:
        # Add a 5-second timeout to the request
        r = requests.get(link, headers=headers, timeout=5)
        soup = BeautifulSoup(r.text, 'html.parser')

        # Get product title
        title = soup.select_one('.product-name h1')
        if title:
            title = title.get_text(strip=True)
            print(f"Title: {title}")
        else:
            title = "N/A"
            print("Title: Not found")
        
        # Get product price
        price_box = soup.select_one('.price-box')
        if price_box:
            # Look for special price first
            special_price = price_box.select_one('.special-price .price')
            if special_price:
                price = special_price.get_text(strip=True)
                print(f"Price (special): {price}")
            else:
                # Then look for regular price
                regular_price = price_box.select_one('.regular-price .price')
                if regular_price:
                    price = regular_price.get_text(strip=True)
                    print(f"Price (regular): {price}")
                else:
                    price = "N/A"
                    print("Price: Not found in price box")
        else:
            price = "N/A"
            print("Price box: Not found")
        
        # Get product description - improved with multiple approaches
        description = ""
        
        # Try tab description first
        desc_tab = soup.select_one('#tab_description_tabbed_contents')
        if desc_tab:
            description = desc_tab.get_text(strip=True)
            print("Description: Found in tab")
        
        # Try short description if tab description is empty
        if not description:
            short_desc = soup.select_one('.short-description .std')
            if short_desc:
                description = short_desc.get_text(strip=True)
                print("Description: Found in short description")
        
        # Try product description div
        if not description:
            desc_div = soup.select_one('.product-description')
            if desc_div:
                description = desc_div.get_text(strip=True)
                print("Description: Found in product-description div")
        
        # Try product collateral areas
        if not description:
            collateral = soup.select_one('.box-collateral')
            if collateral:
                description = collateral.get_text(strip=True)
                print("Description: Found in box-collateral")
        
        # Fall back to entire tab_description div
        if not description:
            full_desc_tab = soup.select_one('#tab_description')
            if full_desc_tab:
                description = full_desc_tab.get_text(strip=True)
                print("Description: Found in full tab_description")
        
        if not description:
            description = "N/A"
            print("Description: Not found in any location")
        else:
            print(f"Description length: {len(description)} chars")

        # Attributes table - improved to handle multiple formats
        attributes = {}
        attribute_source = "None"
        
        # Try method 1: Look in additional tab
        additional_tab = soup.select_one('#tab_additional_tabbed_contents')
        if additional_tab:
            # Try to find product-attribute-specs-table
            attribute_table = additional_tab.select_one('#product-attribute-specs-table')
            if attribute_table:
                print("\nAttributes: Found attribute table in additional tab")
                rows = attribute_table.select('tr')
                print(f"  - Found {len(rows)} rows in attribute table")
                for idx, row in enumerate(rows):
                    th = row.select_one('th')
                    td = row.select_one('td')
                    if th and td:
                        key = th.get_text(strip=True)
                        val = td.get_text(strip=True)
                        attributes[key] = val
                        if idx < 3:  # Show sample of first 3 attributes
                            print(f"  - Attribute {idx+1}: {key} = {val}")
                if attributes:
                    attribute_source = "additional_tab_table"
        
        # Try method 2: Look for data-table directly
        if not attributes:
            data_table = soup.select_one('.data-table')
            if data_table:
                print("\nAttributes: Found data-table")
                rows = data_table.select('tr')
                print(f"  - Found {len(rows)} rows in data-table")
                for idx, row in enumerate(rows):
                    th = row.select_one('th, .label')
                    td = row.select_one('td, .data')
                    if th and td:
                        key = th.get_text(strip=True)
                        val = td.get_text(strip=True)
                        attributes[key] = val
                        if idx < 3:  # Show sample of first 3 attributes
                            print(f"  - Attribute {idx+1}: {key} = {val}")
                if attributes:
                    attribute_source = "data_table"
        
        # Try method 3: Look for attributes directly in product details
        if not attributes:
            product_details = soup.select('.product-shop .product-details li')
            if product_details:
                print("\nAttributes: Found in product-details list")
                print(f"  - Found {len(product_details)} items in details list")
                for idx, detail in enumerate(product_details):
                    text = detail.get_text(strip=True)
                    if ':' in text:
                        key, val = text.split(':', 1)
                        attributes[key.strip()] = val.strip()
                        if idx < 3:  # Show sample of first 3 attributes
                            print(f"  - Attribute {idx+1}: {key.strip()} = {val.strip()}")
                if attributes:
                    attribute_source = "product_details_list"
        
        # Try method 4: Look for additional info boxes
        if not attributes:
            info_boxes = soup.select('.box-additional')
            if info_boxes:
                print(f"\nAttributes: Found {len(info_boxes)} additional info boxes")
            
            for box_idx, box in enumerate(info_boxes):
                box_title = box.select_one('.box-head h2')
                if box_title and 'additional' in box_title.get_text().lower():
                    print(f"  - Processing info box {box_idx+1}: {box_title.get_text(strip=True)}")
                    rows = box.select('tr')
                    print(f"    - Found {len(rows)} rows in this box")
                    
                    for idx, row in enumerate(rows):
                        columns = row.select('td, th')
                        if len(columns) >= 2:
                            key = columns[0].get_text(strip=True)
                            val = columns[1].get_text(strip=True)
                            attributes[key] = val
                            if idx < 3:  # Show sample of first 3 attributes
                                print(f"    - Attribute {idx+1}: {key} = {val}")
                    
                    if attributes and attribute_source == "None":
                        attribute_source = "info_box"
        
        # Try method 5: Look for custom product attributes section
        if not attributes:
            custom_attributes = soup.select('.product-options-bottom .product-custom-attributes li, .product-options .product-custom-attributes li')
            if custom_attributes:
                print(f"\nAttributes: Found {len(custom_attributes)} custom attribute items")
                for idx, attr in enumerate(custom_attributes):
                    text = attr.get_text(strip=True)
                    if ':' in text:
                        key, val = text.split(':', 1)
                        attributes[key.strip()] = val.strip()
                        if idx < 3:  # Show sample of first 3 attributes
                            print(f"  - Attribute {idx+1}: {key.strip()} = {val.strip()}")
                if attributes:
                    attribute_source = "custom_attributes"
        
        print(f"\nAttributes summary: Found {len(attributes)} attributes from source: {attribute_source}")
        if attributes:
            print("Sample attributes:")
            for idx, (key, val) in enumerate(list(attributes.items())[:5]):  # Show first 5 attributes
                print(f"  - {key}: {val}")
        
        # Dump the raw HTML of the additional tab for debugging if no attributes found
        if not attributes:
            print("\nNo attributes found. Looking at page structure...")
            
            # Check if there's any table in the page
            all_tables = soup.select('table')
            print(f"Total tables found on page: {len(all_tables)}")
            
            # Look for any elements with 'attribute' in class or id
            attribute_elements = soup.select('[class*=attribute], [id*=attribute]')
            print(f"Elements with 'attribute' in class/id: {len(attribute_elements)}")
            if attribute_elements:
                print("First few attribute-related elements:")
                for idx, elem in enumerate(attribute_elements[:3]):
                    elem_id = elem.get('id', 'no-id')
                    elem_class = elem.get('class', 'no-class')
                    print(f"  {idx+1}. Element: {elem.name}, ID: {elem_id}, Class: {elem_class}")

        # Breadcrumbs
        breadcrumb_parts = []
        breadcrumb_container = soup.select('.breadcrumbs li a')
        for crumb in breadcrumb_container:
            text = crumb.get_text(strip=True)
            if text.lower() not in ["home"]:
                breadcrumb_parts.append(text)
        breadcrumbs = " > ".join(breadcrumb_parts)
        print(f"Breadcrumbs: {breadcrumbs}")

        # Product images - improved to focus only on the main/base image
        base_image = None
        
        # Try to find the main/base image first with expanded selectors
        base_selectors = [
            '.product-image-gallery img.gallery-image.visible',
            '.product-img-box .product-image img',
            '#image-main',
            '.MagicZoom img',
            '#zoom-btn',
            '.product-img-box img.product-image:first-child',
            '.product-image img',
            '.product-img-box img:first-child',
            'img.photo.image',
            '.main-image img',
            '.fotorama__img',
            '#product_addtocart_form img:first-child'
        ]
        
        for selector in base_selectors:
            base_img = soup.select_one(selector)
            if base_img and 'src' in base_img.attrs:
                base_image = base_img['src']
                print(f"Base image found: {base_image}")
                print(f"  - from selector: {selector}")
                break
        
        # Try to get any hidden data-image attributes, which often contain the full-sized base image
        if not base_image:
            data_images = soup.select('[data-image]')
            for img in data_images:
                if 'data-image' in img.attrs:
                    base_image = img['data-image']
                    print(f"Base image found from data-image: {base_image}")
                    break
        
        # If still not found, try the first image from any gallary
        if not base_image:
            image_sources = [
                '#etalage li img.etalage_thumb_image:first-child',
                '.product-image-gallery img:first-child',
                '.MagicZoom img:first-child', 
                '.more-views img:first-child',
                '.product-image-thumbs img:first-child',
                '.product-img-box img'
            ]
            
            for src in image_sources:
                img = soup.select_one(src)
                if img and 'src' in img.attrs:
                    base_image = img['src']
                    print(f"Base image found from gallery: {base_image}")
                    print(f"  - from selector: {src}")
                    break
                    
        # If we still don't have a base image, try looking for href in links that might point to images
        if not base_image:
            img_links = soup.select('a[href*=".jpg"], a[href*=".jpeg"], a[href*=".png"], a[href*=".gif"]')
            if img_links:
                base_image = img_links[0]['href']
                print(f"Base image found from link href: {base_image}")
        
        print(f"Main image status: {'Found' if base_image else 'Not found'}")
        if base_image:
            # Ensure image URL is absolute
            if base_image.startswith('/'):
                base_url = '/'.join(link.split('/')[:3])  # e.g., https://california-safes.com
                base_image = base_url + base_image
                print(f"Converted to absolute URL: {base_image}")

        # Create the product object
        product_data = {
            'title': title,
            'price': price,
            'url': link,
            'description': description,
            'attributes': attributes,
            'breadcrumbs': breadcrumbs,
            'main_image': base_image
        }
        
        products.append(product_data)

        # Debug print the product data
        print("\nProduct data summary:")
        print(f"  Title: {title}")
        print(f"  Price: {price}")
        print(f"  Main image: {'Yes' if base_image else 'No'}")
        print(f"  Attributes: {len(attributes)}")
        print(f"  Description: {len(description)} chars")
        
        # Write this product to the markdown file immediately
        write_product_to_md(product_data)
        
        # Update the JSON file every 5 products
        if (i + 1) % 5 == 0 or i == len(product_urls) - 1:
            update_json_file()
            print(f"Progress: {i+1}/{len(product_urls)} products processed ({(i+1)/len(product_urls)*100:.1f}%)")

        time.sleep(1)  # polite delay

    except requests.exceptions.Timeout:
        print(f"⚠️ Timeout (>5 seconds) when accessing {link}. Skipping to next product.")
        # Create minimal product data with just the URL
        product_data = {
            'title': f"Failed to load - {link.split('/')[-1]}",
            'price': "N/A (timeout)",
            'url': link,
            'description': "Failed to load product page (timeout)",
            'attributes': {},
            'breadcrumbs': "",
            'main_image': None
        }
        products.append(product_data)
        write_product_to_md(product_data)
        continue
    except Exception as e:
        print(f"Error processing {link}: {e}")
        log_exception(e, f"Processing product {i+1}/{len(product_urls)}")

# Print summary of all collected products
print(f"\n==== Parsed {len(products)} products ====")
for i, product in enumerate(products[:5], 1):  # Print first 5 as sample
    print(f"{i}. {product['title']} - {product['price']} - {len(product['attributes'])} attributes")

print(f"\n✅ Done! All {len(products)} products saved to:")
print(f"  - Markdown: {output_md_file}")
print(f"  - JSON: {output_json_file}")

def log_exception(e, context=""):
    """Log detailed exception information to both console and log file"""
    exc_type, exc_value, exc_traceback = sys.exc_info()
    trace_lines = traceback.format_exception(exc_type, exc_value, exc_traceback)
    trace_text = ''.join(trace_lines)
    
    error_msg = f"{'='*50}\nEXCEPTION in {context}\n{'-'*50}\n"
    error_msg += f"Error Type: {exc_type.__name__}\n"
    error_msg += f"Error Message: {str(e)}\n"
    error_msg += f"Timestamp: {datetime.now().strftime('%Y-%m-%d %H:%M:%S.%f')[:-3]}\n"
    error_msg += f"Exception occurred in file: {traceback.extract_tb(exc_traceback)[-1].filename}\n"
    error_msg += f"Line number: {traceback.extract_tb(exc_traceback)[-1].lineno}\n"
    error_msg += f"Function: {traceback.extract_tb(exc_traceback)[-1].name}\n"
    
    if hasattr(e, '__traceback__') and e.__traceback__ is not None:
        tb = e.__traceback__
        while tb.tb_next:
            tb = tb.tb_next
        frame = tb.tb_frame
        error_msg += f"\nLocal variables at the point of exception:\n{'-'*50}\n"
        for key, value in frame.f_locals.items():
            # Limit the output size for each variable
            try:
                value_str = str(value)
                if len(value_str) > 500:
                    value_str = value_str[:500] + "... [truncated]"
                error_msg += f"{key} = {value_str}\n"
            except:
                error_msg += f"{key} = <unprintable value>\n"
    
    error_msg += f"\nStack Trace:\n{trace_text}\n"
    error_msg += f"{'='*50}\n"
    
    print(error_msg)
    with open(trace_log_file, "a", encoding="utf-8") as f:
        f.write(error_msg)
