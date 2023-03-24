import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPostMeta } from '@knowii/client-ui';

const Story: ComponentMeta<typeof BlogPostMeta> = {
  component: BlogPostMeta,
  title: 'BlogPostMeta',
};
export default Story;

const Template: ComponentStory<typeof BlogPostMeta> = (args) => <BlogPostMeta {...args}></BlogPostMeta>;

export const Primary = Template.bind({});
Primary.args = {
  author: 'Foo Bar',
  authorLink: 'https://twitter.com/dSebastien',
  publishedAt: '2023-02-10',
};
