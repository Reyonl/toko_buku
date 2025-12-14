-- Optional: Add ISBN column to books table
-- Run this SQL if you want to store ISBN information for books

ALTER TABLE books 
ADD COLUMN isbn VARCHAR(20) NULL AFTER description;

-- Add index for faster ISBN searches
CREATE INDEX idx_isbn ON books(isbn);

