/*
  Warnings:

  - Made the column `email` on table `clients` required. This step will fail if there are existing NULL values in that column.
  - Made the column `image_url` on table `clients` required. This step will fail if there are existing NULL values in that column.
  - Made the column `phone` on table `clients` required. This step will fail if there are existing NULL values in that column.

*/
-- AlterTable
ALTER TABLE "public"."clients" ALTER COLUMN "email" SET NOT NULL,
ALTER COLUMN "email" SET DEFAULT '',
ALTER COLUMN "image_url" SET NOT NULL,
ALTER COLUMN "image_url" SET DEFAULT '',
ALTER COLUMN "phone" SET NOT NULL,
ALTER COLUMN "phone" SET DEFAULT '';
