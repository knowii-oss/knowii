/*
  Warnings:

  - A unique constraint covering the columns `[slug]` on the table `communities` will be added. If there are existing duplicate values, this will fail.
  - Added the required column `slug` to the `communities` table without a default value. This is not possible if the table is not empty.

*/
-- AlterTable
ALTER TABLE "public"."communities" ADD COLUMN     "slug" TEXT NOT NULL;

-- CreateIndex
CREATE UNIQUE INDEX "communities_slug_key" ON "public"."communities"("slug");
