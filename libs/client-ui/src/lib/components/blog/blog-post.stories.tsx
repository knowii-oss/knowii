import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { BlogPost } from './blog-post';

const Story: ComponentMeta<typeof BlogPost> = {
  component: BlogPost,
  title: 'BlogPost',
};
export default Story;

const Template: ComponentStory<typeof BlogPost> = (args) => <BlogPost {...args} />;

export const Primary = Template.bind({});
Primary.args = {
  title: 'Foo Bar Baz',
  publishedOn: '2023-03-23',
  slug: 'foo-bar-baz',
  summary: 'Foo Bar Baz!',
  image: '/images/blog/blog-demo-cover.png',
};
