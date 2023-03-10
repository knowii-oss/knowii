import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { EditableText } from './editable-text';

const Story: ComponentMeta<typeof EditableText> = {
  component: EditableText,
  title: 'EditableText',
};
export default Story;

const Template: ComponentStory<typeof EditableText> = (args) => <EditableText {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
